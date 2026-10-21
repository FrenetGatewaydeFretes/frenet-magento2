<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\App\ResourceConnection;

/**
 * One page of GET /shipments (every label of the Frenet account, also the ones bought in the Frenet panel), as the
 * "Manage your labels" and "Printing" screens list it: local order, status, service, price, tracking and the
 * "Notes" of the order.
 */
class LabelRows
{
    /**
     * @param Client $client
     * @param LabelsConfig $config
     * @param ResourceConnection $resource
     * @param IssueStore $issues
     * @param ResponseReader $reader
     * @param FrenetOrderId $orderIds
     * @param StatusName $statusNames
     */
    public function __construct(
        private readonly Client $client,
        private readonly LabelsConfig $config,
        private readonly ResourceConnection $resource,
        private readonly IssueStore $issues,
        private readonly ResponseReader $reader,
        private readonly FrenetOrderId $orderIds,
        private readonly StatusName $statusNames
    ) {
    }

    /**
     * Rows of the page (after the filters), total of the account, count per status and error text.
     *
     * @param int $page 0-based, like the Frenet API.
     * @param int $perPage
     * @param int[] $allowed Statuses the screen shows (empty: all).
     * @param int $filter Status chosen in the screen (0: all).
     * @return array{total: int, rows: mixed[], error: string, counts: int[]}
     */
    public function page(int $page, int $perPage, array $allowed, int $filter): array
    {
        $data = ['total' => 0, 'rows' => [], 'error' => '', 'counts' => []];
        if (!$this->config->isAvailable()) {
            return $data;
        }
        try {
            $res = $this->client->whitelabel('GET', '/shipments?page=' . $page . '&per_page=' . $perPage);
        } catch (LabelException $e) {
            // Live 2026-10-20: an account without labels answers 404 {"message":null} instead of an empty page.
            if ($e->getCode() !== 404) {
                $data['error'] = $e->getMessage();
            }
            return $data;
        }
        $data['total'] = (int) ($this->reader->field($res, 'total') ?? 0);
        $list = (array) ($this->reader->field($res, 'shipments') ?? []);
        $orders = $this->localOrders(array_map(
            fn ($shipment) => $this->orderIds->incrementId((string) ($shipment['orderId'] ?? '')),
            $list
        ));
        $issues = $this->issues->forOrders(array_values($orders));
        foreach ($list as $shipment) {
            $status = (int) ($shipment['shipmentStatus'] ?? 0);
            if ($allowed && !in_array($status, $allowed, true)) {
                continue;
            }
            $data['counts'][$status] = ($data['counts'][$status] ?? 0) + 1;
            if ($filter && $status !== $filter) {
                continue;
            }
            $data['rows'][] = $this->row((array) $shipment, $orders, $issues);
        }
        return $data;
    }

    /**
     * One shipment as the screens need it.
     *
     * @param mixed[] $shipment Frenet shipment.
     * @param array<string,int> $orders Local order id by increment id.
     * @param array<int,array{message:string,at:string}> $issues "Notes" by local order id.
     * @return mixed[]
     */
    private function row(array $shipment, array $orders, array $issues): array
    {
        $status = (int) ($shipment['shipmentStatus'] ?? 0);
        $quote = (array) ($shipment['quotation'] ?? []);
        $volume = (array) (((array) ($shipment['volumes'] ?? []))[0] ?? []);
        $orderId = (string) ($shipment['orderId'] ?? '');
        $local = $orders[$this->orderIds->incrementId($orderId)] ?? null;
        $paid = in_array($status, ShipmentService::PAID, true);
        $trackingUrl = (string) ($shipment['trackingUrl'] ?? '');
        return [
            'id' => (string) ($shipment['shipmentId'] ?? ''),
            'order' => $orderId,
            'order_id' => $local,
            // "Notes" of the order, on its unpaid label only (a paid or cancelled one went past that error).
            'issue' => $local && in_array($status, ShipmentService::UNPAID, true) ? ($issues[$local] ?? null) : null,
            'status' => $status,
            'status_label' => $this->statusNames->label($status),
            'tone' => $this->statusNames->tone($status),
            'service' => trim(($quote['carrierCode'] ?? '') . ' ' . ($quote['shippingServiceCode'] ?? '')),
            'carrier_code' => (string) ($quote['carrierCode'] ?? ''),
            'carrier' => (string) ($quote['carrier'] ?? ''),
            'service_code' => (string) ($quote['shippingServiceCode'] ?? ''),
            'price' => (float) ($quote['shippingPrice'] ?? $quote['platformShippingPrice'] ?? 0),
            'tracking_url' => $trackingUrl,
            'tracking' => (string) preg_replace('#^.*/#', '', rtrim($trackingUrl, '/')),
            'label_url' => $paid ? (string) ($shipment['labelUrl'] ?? '') : '',
            'can_pay' => in_array(
                $status,
                [ShipmentService::STATUS_CREATED, ShipmentService::STATUS_PENDING_PAYMENT],
                true
            ),
            'can_print' => $paid,
            'can_cancel' => !in_array($status, LabelStore::GONE, true),
            'date' => (string) ($volume['modifiedOn'] ?? ''),
        ];
    }

    /**
     * Local orders (increment id → entity id) among the Frenet order ids.
     *
     * @param string[] $incrementIds
     * @return array<string, int>
     */
    private function localOrders(array $incrementIds): array
    {
        $ids = array_values(array_filter(array_unique($incrementIds), fn ($value): bool => (string) $value !== ''));
        if (!$ids) {
            return [];
        }
        $connection = $this->resource->getConnection('sales');
        return array_map('intval', $connection->fetchPairs($connection->select()
            ->from($this->resource->getTableName('sales_order'), ['increment_id', 'entity_id'])
            ->where('increment_id IN (?)', $ids)));
    }
}
