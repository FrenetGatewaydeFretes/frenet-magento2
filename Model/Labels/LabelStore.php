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
 * Frenet shipments created from orders (table frenet_shipping_label). The Frenet shipment id is the only link
 * between an order and its label: it is saved right after creating, before paying.
 */
class LabelStore
{
    /** Statuses of a shipment that no longer exists for the store (cancel scheduled, cancelled, deleted). */
    public const GONE = [6, 7, 9];

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Row of a Frenet shipment.
     *
     * @param string $shipmentId
     * @return array|null
     */
    public function get(string $shipmentId): ?array
    {
        $db = $this->db();
        $row = $db->fetchRow($db->select()->from($this->table())->where('shipment_id = ?', $shipmentId));
        return $row ?: null;
    }

    /**
     * Latest shipment of the order that still exists (not cancelled or deleted).
     *
     * @param int $orderId
     * @return array|null
     */
    public function activeForOrder(int $orderId): ?array
    {
        $db = $this->db();
        $row = $db->fetchRow($db->select()->from($this->table())
            ->where('order_id = ?', $orderId)
            ->where('status NOT IN (?)', self::GONE)
            ->order('entity_id DESC')
            ->limit(1));
        return $row ?: null;
    }

    /**
     * Order ids (among the given ones) that already have a shipment that still exists.
     *
     * @param int[] $orderIds
     * @return int[]
     */
    public function ordersWithActive(array $orderIds): array
    {
        if (!$orderIds) {
            return [];
        }
        $db = $this->db();
        return array_map('intval', $db->fetchCol($db->select()->distinct()->from($this->table(), 'order_id')
            ->where('order_id IN (?)', $orderIds)
            ->where('status NOT IN (?)', self::GONE)));
    }

    /**
     * Creates or updates the row of a shipment.
     *
     * @param string $shipmentId
     * @param array $data
     * @return void
     */
    public function save(string $shipmentId, array $data): void
    {
        $data['shipment_id'] = $shipmentId;
        $this->db()->insertOnDuplicate($this->table(), $data, array_keys(array_diff_key($data, ['shipment_id' => 1])));
    }

    /**
     * Shipments bought here, paid, still without label, and polled less than $maxAttempts times.
     *
     * @param int $maxAttempts
     * @return array
     */
    public function waitingForLabel(int $maxAttempts): array
    {
        $db = $this->db();
        return $db->fetchAll($db->select()->from($this->table())
            ->where('journey = ?', LabelsConfig::JOURNEY_HERE)
            ->where('status IN (?)', [2, 4, 5, 18])
            ->where('(label_url IS NULL OR label_url = ?)', '')
            ->where('poll_attempts < ?', $maxAttempts));
    }

    /**
     * Shipments with a tracking code that still exist, newest first.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function withTracking(int $limit, int $offset = 0): array
    {
        $db = $this->db();
        return $db->fetchAll($db->select()->from(['l' => $this->table()])
            ->joinLeft(
                ['o' => $this->resource->getTableName('sales_order')],
                'o.entity_id = l.order_id',
                ['increment_id', 'shipping_method']
            )
            ->where("l.tracking_code IS NOT NULL AND l.tracking_code <> ''")
            ->where('l.status NOT IN (?)', self::GONE)
            ->order('l.entity_id DESC')
            ->limit($limit, $offset));
    }

    /**
     * Connection of the sales resource.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function db()
    {
        return $this->resource->getConnection('sales');
    }

    /**
     * Table name.
     *
     * @return string
     */
    private function table(): string
    {
        return $this->resource->getTableName('frenet_shipping_label');
    }
}
