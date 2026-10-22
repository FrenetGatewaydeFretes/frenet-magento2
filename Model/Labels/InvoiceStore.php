<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * NF-e access key per order (table frenet_shipping_invoice).
 */
class InvoiceStore
{
    /**
     * @param ResourceConnection $resource
     * @param NfeKey $nfeKeys
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly NfeKey $nfeKeys
    ) {
    }

    /**
     * Stored key of the order, or ''.
     *
     * @param int $orderId
     * @return string
     */
    public function get(int $orderId): string
    {
        $connection = $this->resource->getConnection('sales');
        return (string) $connection->fetchOne(
            $connection->select()->from($this->table(), 'access_key')->where('order_id = ?', $orderId)
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
        $connection = $this->resource->getConnection('sales');
        if ($key === '') {
            $connection->delete($this->table(), ['order_id = ?' => $orderId]);
            return;
        }
        $connection->insertOnDuplicate($this->table(), ['order_id' => $orderId, 'access_key' => $key], ['access_key']);
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

    /**
     * Saves a pasted NF-e key after checking it; an empty key saves nothing.
     *
     * @param int $orderId
     * @param string $pasted Key as typed (spaces and dots allowed).
     * @return void
     * @throws LocalizedException When the key is not a valid NF-e access key.
     */
    public function saveChecked(int $orderId, string $pasted): void
    {
        $key = $this->nfeKeys->normalize($pasted);
        if ($key === '') {
            return;
        }
        if (!$this->nfeKeys->isValid($key)) {
            throw new LocalizedException(
                __('The NF-e key is not valid (44 digits, the last one is a check digit). Nothing was created.')
            );
        }
        $this->save($orderId, $key);
    }
}
