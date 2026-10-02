<?php
/**
 * Frenet Shipping Gateway — automatic tracking: reads the Frenet events of the Frenet tracking codes,
 * stores them (frenet_shipping_tracking) and, when enabled, moves the order status and tells the customer.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\TrackingSync;

use Frenet\Shipping\Model\Extras\Settings;
use Frenet\Shipping\Model\Labels\Client;
use Frenet\Shipping\Model\Labels\LabelException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderCommentSender;
use Psr\Log\LoggerInterface;

class Sync
{
    private const BATCH = 100;
    /** A code is checked again after this many minutes. */
    private const REFRESH_MINUTES = 25;

    /**
     * @param ResourceConnection $resource
     * @param Client $client
     * @param Json $json
     * @param Settings $settings
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderCommentSender $commentSender
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Client $client,
        private readonly Json $json,
        private readonly Settings $settings,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCommentSender $commentSender,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Frenet tracking codes due for a check (all of the order when $orderId is given).
     *
     * @param int|null $orderId
     * @return array
     */
    public function pendingTracks(?int $orderId = null): array
    {
        $db = $this->resource->getConnection('sales');
        $select = $db->select()
            ->from(['t' => $this->resource->getTableName('sales_shipment_track')], ['track_id' => 'entity_id', 'order_id', 'track_number'])
            ->join(['o' => $this->resource->getTableName('sales_order')], 'o.entity_id = t.order_id', ['shipping_method', 'status'])
            ->joinLeft(['f' => $this->table()], 'f.track_id = t.entity_id', ['events', 'synced_at'])
            ->joinLeft(
                ['l' => $this->resource->getTableName('frenet_shipping_label')],
                'l.order_id = t.order_id AND l.tracking_code = t.track_number',
                ['label_service' => 'service_code']
            )
            ->where('t.carrier_code = ?', 'frenetshipping')
            ->order(new \Zend_Db_Expr('f.synced_at IS NOT NULL'))
            ->order('f.synced_at ASC')
            ->limit(self::BATCH);
        if ($orderId !== null) {
            $select->where('t.order_id = ?', $orderId);
        } else {
            $select->where('o.state NOT IN (?)', [Order::STATE_CANCELED, Order::STATE_CLOSED])
                ->where('o.status NOT IN (?)', StatusMapper::FINAL)
                ->where('f.synced_at IS NULL OR f.synced_at < ?', gmdate('Y-m-d H:i:s', time() - self::REFRESH_MINUTES * 60));
        }
        return $db->fetchAll($select);
    }

    /**
     * Checks the due codes (or all codes of one order).
     *
     * @param int|null $orderId
     * @return array{synced: int, errors: int, changed: int}
     */
    public function run(?int $orderId = null): array
    {
        $out = ['synced' => 0, 'errors' => 0, 'changed' => 0];
        foreach ($this->pendingTracks($orderId) as $row) {
            $service = (string) ($row['label_service'] ?: StatusMapper::serviceCode((string) $row['shipping_method']));
            if ($service === '') {
                continue;
            }
            try {
                $data = $this->client->tracking($service, (string) $row['track_number']);
            } catch (LabelException $e) {
                $out['errors']++;
                $this->logger->warning('Frenet tracking ' . $row['track_number'] . ': ' . $e->getMessage());
                continue;
            }
            $out['synced']++;
            $out['changed'] += (int) $this->recordEvents(
                (int) $row['track_id'],
                (int) $row['order_id'],
                $service,
                (string) $row['track_number'],
                (array) ($data['TrackingEvents'] ?? []),
                false
            );
        }
        return $out;
    }

    /**
     * Stores the events (Frenet format) and applies the status on a new latest event.
     *
     * @param int $trackId
     * @param int $orderId
     * @param string $service
     * @param string $number
     * @param array $rawEvents
     * @param bool $merge Keep the stored events.
     * @return bool Whether the order status changed.
     */
    public function recordEvents(int $trackId, int $orderId, string $service, string $number, array $rawEvents, bool $merge): bool
    {
        $db = $this->resource->getConnection('sales');
        $stored = (string) $db->fetchOne($db->select()->from($this->table(), 'events')->where('track_id = ?', $trackId));
        $previous = $stored !== '' ? (array) $this->json->unserialize($stored) : [];
        $events = $merge ? $previous : [];
        foreach ($rawEvents as $e) {
            $events[] = [
                'date' => (string) ($e['EventDateTime'] ?? $e['date'] ?? ''),
                'location' => (string) ($e['EventLocation'] ?? $e['location'] ?? ''),
                'description' => (string) ($e['EventDescription'] ?? $e['description'] ?? ''),
                'type' => (string) ($e['EventType'] ?? $e['type'] ?? ''),
            ];
        }
        $unique = [];
        foreach ($events as $e) {
            $unique[$e['date'] . '|' . $e['description']] = $e;
        }
        $events = array_values($unique);
        usort($events, static fn ($a, $b) => StatusMapper::eventTime($a['date']) <=> StatusMapper::eventTime($b['date']));
        $last = $events ? end($events) : null;
        $db->insertOnDuplicate($this->table(), [
            'track_id' => $trackId,
            'order_id' => $orderId,
            'service_code' => $service,
            'track_number' => $number,
            'events' => $this->json->serialize($events),
            'last_description' => $last ? mb_substr($last['description'], 0, 255) : null,
            'synced_at' => gmdate('Y-m-d H:i:s'),
        ], ['events', 'last_description', 'synced_at', 'service_code']);
        if ($last === null || count($events) <= count($previous) || !$this->settings->autoStatus()) {
            return false;
        }
        return $this->applyStatus($orderId, $last);
    }

    /**
     * Moves the order to the status of the event; tells the customer when enabled.
     *
     * @param int $orderId
     * @param array $event
     * @return bool
     */
    public function applyStatus(int $orderId, array $event): bool
    {
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);
        if (in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_CLOSED, Order::STATE_HOLDED], true)) {
            return false;
        }
        $status = StatusMapper::statusFor((string) $event['description'], (string) ($event['type'] ?? ''));
        if ($status === '' || $order->getStatus() === $status || in_array($order->getStatus(), StatusMapper::FINAL, true)) {
            return false;
        }
        $notify = $this->settings->notifyCustomer();
        $detail = trim($event['description'] . ' · ' . $event['date'] . ' ' . $event['location'], ' ·');
        $comment = (string) __('Tracking: %1', $detail);
        $history = $order->addCommentToStatusHistory($comment, $status, true);
        $history->setIsCustomerNotified($notify);
        $this->orderRepository->save($order);
        if ($notify) {
            try {
                $this->commentSender->send($order, true, $comment);
            } catch (\Throwable $e) {
                $this->logger->warning('Frenet tracking e-mail to order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            }
        }
        return true;
    }

    /**
     * Stored events of an order, per code, newest first.
     *
     * @param int $orderId
     * @return array
     */
    public function forOrder(int $orderId): array
    {
        $db = $this->resource->getConnection('sales');
        $rows = $db->fetchAll($db->select()->from($this->table())->where('order_id = ?', $orderId));
        return array_map(fn ($r) => $r + ['list' => array_reverse((array) $this->json->unserialize((string) ($r['events'] ?: '[]')))], $rows);
    }

    /**
     * Tracking codes of the store with their latest event, for the Tracking page.
     *
     * @param int $limit
     * @param int $offset
     * @param string $filter all|moving|delivered|returning|never
     * @return array{rows: array, total: int, never: int}
     */
    public function overview(int $limit, int $offset, string $filter = 'all'): array
    {
        $db = $this->resource->getConnection('sales');
        $base = $db->select()
            ->from(['t' => $this->resource->getTableName('sales_shipment_track')], ['track_id' => 'entity_id', 'order_id', 'track_number', 'created_at'])
            ->join(['o' => $this->resource->getTableName('sales_order')], 'o.entity_id = t.order_id', ['increment_id', 'status', 'shipping_description'])
            ->joinLeft(['f' => $this->table()], 'f.track_id = t.entity_id', ['last_description', 'synced_at', 'events'])
            ->where('t.carrier_code = ?', 'frenetshipping');
        $never = (int) $db->fetchOne((clone $base)->reset(\Magento\Framework\DB\Select::COLUMNS)->columns('COUNT(*)')->where('f.synced_at IS NULL'));
        $map = [
            'delivered' => StatusMapper::DELIVERED,
            'returning' => StatusMapper::RETURNING,
            'pickup' => StatusMapper::PICKUP,
            'moving' => StatusMapper::IN_TRANSIT,
        ];
        if (isset($map[$filter])) {
            $base->where('o.status = ?', $map[$filter]);
        } elseif ($filter === 'never') {
            $base->where('f.synced_at IS NULL');
        }
        $total = (int) $db->fetchOne((clone $base)->reset(\Magento\Framework\DB\Select::COLUMNS)->columns('COUNT(*)'));
        $rows = $db->fetchAll($base->order('t.entity_id DESC')->limit($limit, $offset));
        foreach ($rows as &$r) {
            $list = (array) $this->json->unserialize((string) ($r['events'] ?: '[]'));
            $r['last'] = $list ? end($list) : null;
            $r['count'] = count($list);
            unset($r['events']);
        }
        return ['rows' => $rows, 'total' => $total, 'never' => $never];
    }

    /**
     * Table name.
     *
     * @return string
     */
    private function table(): string
    {
        return $this->resource->getTableName('frenet_shipping_tracking');
    }
}
