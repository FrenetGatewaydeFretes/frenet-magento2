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
 * NF-e access key per order (table frenet_shipping_invoice).
 */
class InvoiceStore
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Stored key of the order, or ''.
     *
     * @param int $orderId
     * @return string
     */
    public function get(int $orderId): string
    {
        $db = $this->resource->getConnection('sales');
        return (string) $db->fetchOne(
            $db->select()->from($this->table(), 'access_key')->where('order_id = ?', $orderId)
        );
    }

    /**
     * Saves the key ('' removes it).
     *
     * @param int $orderId
     * @param string $key
     * @return void
     */
    public function save(int $orderId, string $key): void
    {
        $db = $this->resource->getConnection('sales');
        if ($key === '') {
            $db->delete($this->table(), ['order_id = ?' => $orderId]);
            return;
        }
        $db->insertOnDuplicate($this->table(), ['order_id' => $orderId, 'access_key' => $key], ['access_key']);
    }

    /**
     * Table name.
     *
     * @return string
     */
    private function table(): string
    {
        return $this->resource->getTableName('frenet_shipping_invoice');
    }
}
