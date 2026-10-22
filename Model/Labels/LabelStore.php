<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

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
     * @return mixed[]|null
     */
    public function get(string $shipmentId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->table())->where('shipment_id = ?', $shipmentId)
        );
        return $row ?: null;
    }

    /**
     * Latest shipment of the order that still exists (not cancelled or deleted).
     *
     * @param int $orderId
     * @return mixed[]|null
     */
    public function activeForOrder(int $orderId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow($connection->select()->from($this->table())
            ->where('order_id = ?', $orderId)
            ->where('status NOT IN (?)', self::GONE)
            ->order('entity_id DESC')
            ->limit(1));
        return $row ?: null;
    }

    /**
     * How many Frenet shipments the order ever had here (cancelled ones too).
     *
     * @param int $orderId
     * @return int
     */
    public function countForOrder(int $orderId): int
    {
        $connection = $this->connection();
        $select = $connection->select()->from($this->table(), 'COUNT(*)')->where('order_id = ?', $orderId);
        return (int) $connection->fetchOne($select);
    }

    /**
     * Orders whose Frenet labels were all cancelled or deleted, newest first.
     *
     * They can get a new label even when Magento already shipped them with the cancelled one.
     *
     * @param int $limit
     * @return int[]
     */
    public function onlyGone(int $limit = 200): array
    {
        $connection = $this->connection();
        $active = $connection->quoteInto('SUM(status NOT IN (?)) = 0', self::GONE);
        $select = $connection->select()->from($this->table(), 'order_id');
        $select->group('order_id')->having($active)->order('MAX(entity_id) DESC')->limit($limit);
        return array_map('intval', $connection->fetchCol($select));
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
        $connection = $this->connection();
        return array_map('intval', $connection->fetchCol($connection->select()->distinct()
            ->from($this->table(), 'order_id')
            ->where('order_id IN (?)', $orderIds)
            ->where('status NOT IN (?)', self::GONE)));
    }

    /**
     * Creates or updates the row of a shipment.
     *
     * @param string $shipmentId
     * @param mixed[] $data
     * @return void
     */
    public function save(string $shipmentId, array $data): void
    {
        $data['shipment_id'] = $shipmentId;
        $this->connection()->insertOnDuplicate(
            $this->table(),
            $data,
            array_keys(array_diff_key($data, ['shipment_id' => 1]))
        );
    }

    /**
     * Shipments bought here, paid, still without label, and polled less than $maxAttempts times.
     *
     * @param int $maxAttempts
     * @return array<int, mixed[]>
     */
    public function waitingForLabel(int $maxAttempts): array
    {
        $connection = $this->connection();
        return $connection->fetchAll($connection->select()->from($this->table())
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
     * @return array<int, mixed[]>
     */
    public function withTracking(int $limit, int $offset = 0): array
    {
        $connection = $this->connection();
        return $connection->fetchAll($connection->select()->from(['l' => $this->table()])
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
     * @return AdapterInterface
     */
    private function connection()
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
