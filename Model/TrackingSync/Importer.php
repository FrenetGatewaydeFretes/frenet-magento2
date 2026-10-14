<?php
/**
 * Frenet Shipping Gateway — bulk tracking codes: preview what each pasted line will do, then apply it.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\TrackingSync;

use Frenet\Shipping\Model\Labels\Shipper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class Importer
{
    public const SHIP = 'ship';
    public const ADD = 'add';
    public const EXISTS = 'exists';
    public const NOT_FOUND = 'not_found';
    public const CANNOT = 'cannot';

    /**
     * @param CollectionFactory $orders
     * @param Shipper $shipper
     */
    public function __construct(
        private readonly CollectionFactory $orders,
        private readonly Shipper $shipper
    ) {
    }

    /**
     * What will happen to each row, without changing anything.
     *
     * @param array $rows [{order, code}]
     * @return array [{order, code, action, order_id, customer}]
     */
    public function preview(array $rows): array
    {
        $out = [];
        $byIncrement = $this->load(array_column($rows, 'order'));
        foreach ($rows as $r) {
            $order = $byIncrement[$r['order']] ?? null;
            $out[] = ['order' => $r['order'], 'code' => $r['code']] + $this->decide($order, $r['code']);
        }
        return $out;
    }

    /**
     * Applies the rows that can be applied.
     *
     * @param array $rows [{order, code}]
     * @param bool $notify
     * @return array{shipped: int, added: int, skipped: int, errors: array<int, string>}
     */
    public function apply(array $rows, bool $notify): array
    {
        $out = ['shipped' => 0, 'added' => 0, 'skipped' => 0, 'errors' => []];
        foreach ($this->preview($rows) as $r) {
            if (!in_array($r['action'], [self::SHIP, self::ADD], true)) {
                $out['skipped']++;
                continue;
            }
            try {
                /** @var Order $order */
                $order = $this->load([$r['order']])[$r['order']];
                $result = $this->shipper->attach($order, $r['code'], $notify);
                if ($result === Shipper::SHIPPED) {
                    $out['shipped']++;
                } elseif ($result === Shipper::ADDED) {
                    $out['added']++;
                } else {
                    $out['skipped']++;
                }
            } catch (\Throwable $e) {
                $out['errors'][] = (string) __('Order #%1: %2', $r['order'], $e->getMessage());
            }
        }
        return $out;
    }

    /**
     * Action for one order and code.
     *
     * @param Order|null $order
     * @param string $code
     * @return array{action: string, order_id: int, customer: string}
     */
    private function decide(?Order $order, string $code): array
    {
        if (!$order) {
            return ['action' => self::NOT_FOUND, 'order_id' => 0, 'customer' => ''];
        }
        $info = ['order_id' => (int) $order->getId(), 'customer' => trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname())];
        foreach ($order->getTracksCollection() as $t) {
            if (strtoupper((string) $t->getTrackNumber()) === $code) {
                return ['action' => self::EXISTS] + $info;
            }
        }
        if ($order->canShip()) {
            return ['action' => self::SHIP] + $info;
        }
        return ['action' => $order->getShipmentsCollection()->getSize() ? self::ADD : self::CANNOT] + $info;
    }

    /**
     * Orders by increment id.
     *
     * @param string[] $incrementIds
     * @return array<string, Order>
     */
    private function load(array $incrementIds): array
    {
        $ids = array_values(array_unique(array_filter($incrementIds, 'strlen')));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach ($this->orders->create()->addFieldToFilter('increment_id', ['in' => $ids]) as $order) {
            $out[(string) $order->getIncrementId()] = $order;
        }
        return $out;
    }
}
