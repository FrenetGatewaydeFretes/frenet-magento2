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
 * "Notes" of the labels screens: why the last label of an order failed (carrier refusal, no balance, payment without
 * answer…). Kept per order until its label goes through, is cancelled or a new one is created.
 */
class IssueStore
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Issues of the given orders.
     *
     * @param int[] $orderIds
     * @return array<int, array{message: string, at: string}> By order id.
     */
    public function forOrders(array $orderIds): array
    {
        $orderIds = array_values(array_filter(array_map('intval', $orderIds)));
        if (!$orderIds) {
            return [];
        }
        $connection = $this->connection();
        $out = [];
        $select = $connection->select()->from($this->table())->where('order_id IN (?)', $orderIds);
        foreach ($connection->fetchAll($select) as $row) {
            $out[(int) $row['order_id']] = ['message' => (string) $row['message'], 'at' => (string) $row['created_at']];
        }
        return $out;
    }

    /**
     * Issue of one order, or null.
     *
     * @param int $orderId
     * @return array{message: string, at: string}|null
     */
    public function get(int $orderId): ?array
    {
        return $this->forOrders([$orderId])[$orderId] ?? null;
    }

    /**
     * Keeps why the label of the order failed.
     *
     * @param int $orderId
     * @param string $message
     * @return void
     */
    public function set(int $orderId, string $message): void
    {
        $message = trim(strip_tags($message));
        if ($orderId <= 0 || $message === '') {
            return;
        }
        $this->connection()->insertOnDuplicate(
            $this->table(),
            ['order_id' => $orderId, 'message' => mb_substr($message, 0, 1000), 'created_at' => gmdate('Y-m-d H:i:s')],
            ['message', 'created_at']
        );
    }

    /**
     * The label went through (or the shopkeeper acted on it): forget the previous error.
     *
     * @param int $orderId
     * @return void
     */
    public function clear(int $orderId): void
    {
        $this->connection()->delete($this->table(), ['order_id = ?' => $orderId]);
    }

    /**
     * Connection of the sales resource.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
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
        return $this->resource->getTableName('frenet_shipping_issue');
    }
}
