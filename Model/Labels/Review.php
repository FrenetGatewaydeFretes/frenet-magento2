<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * Data of "Create shipment": orders that can get a Frenet shipment, and for the selected ones every carrier with
 * price and delivery time (quoted now), the preselected service, the checks (OK/NOK) and the totals.
 */
class Review
{
    public const MAX_ORDERS = 50;

    /**
     * @param CollectionFactory $orders
     * @param LabelStore $store
     * @param InvoiceStore $invoices
     * @param Client $client
     * @param PayloadBuilder $payload
     * @param LabelsConfig $config
     * @param Wallet $wallet
     * @param RegionResolver $regions
     */
    public function __construct(
        private readonly CollectionFactory $orders,
        private readonly LabelStore $store,
        private readonly InvoiceStore $invoices,
        private readonly Client $client,
        private readonly PayloadBuilder $payload,
        private readonly LabelsConfig $config,
        private readonly Wallet $wallet,
        private readonly RegionResolver $regions
    ) {
    }

    /**
     * Recent orders still waiting for a Frenet shipment (not shipped, not cancelled, no shipment yet).
     *
     * @param string $search Order number or customer name.
     * @param int $limit
     * @return array
     */
    public function candidates(string $search = '', int $limit = 50): array
    {
        $collection = $this->orders->create()
            ->addFieldToFilter('state', ['in' => [Order::STATE_NEW, Order::STATE_PROCESSING]])
            ->addFieldToFilter('is_virtual', 0)
            ->setOrder('entity_id', 'DESC')
            ->setPageSize($limit * 2);
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $collection->addFieldToFilter(
                ['increment_id', 'customer_firstname', 'customer_lastname'],
                [['like' => $like], ['like' => $like], ['like' => $like]]
            );
        }
        $ids = array_map('intval', $collection->getAllIds());
        $taken = array_flip($this->store->ordersWithActive($ids));
        $out = [];
        foreach ($collection as $order) {
            /** @var Order $order */
            if (isset($taken[(int) $order->getId()]) || !$order->canShip()) {
                continue;
            }
            $address = $order->getShippingAddress();
            $out[] = [
                'id' => (int) $order->getId(),
                'number' => (string) $order->getIncrementId(),
                'date' => (string) $order->getCreatedAt(),
                'customer' => $address ? trim($address->getFirstname() . ' ' . $address->getLastname()) : '',
                'city' => $address ? trim($address->getCity() . ' / ' . $this->regionOf($address), ' /') : '',
                'shipping' => (string) $order->getShippingDescription(),
                'frenet' => self::serviceCode((string) $order->getShippingMethod()) !== '',
                'total' => (float) $order->getGrandTotal(),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Comparison of the selected orders.
     *
     * @param int[] $orderIds
     * @return array{rows: array, header: array, wallet: ?array, truncated: bool}
     */
    public function review(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        $truncated = count($orderIds) > self::MAX_ORDERS;
        $orderIds = array_slice($orderIds, 0, self::MAX_ORDERS);
        $rows = [];
        if ($orderIds) {
            $taken = array_flip($this->store->ordersWithActive($orderIds));
            $collection = $this->orders->create()->addFieldToFilter('entity_id', ['in' => $orderIds]);
            foreach ($collection as $order) {
                /** @var Order $order */
                $rows[] = $this->row($order, isset($taken[(int) $order->getId()]));
            }
        }
        $quoted = array_values(array_filter($rows, static fn ($r) => $r['ok']));
        return [
            'rows' => $rows,
            'header' => Compare::carriers(array_column($quoted, 'services')),
            'wallet' => $this->wallet->get(true),
            'truncated' => $truncated,
        ];
    }

    /**
     * Frenet service code of a shipping method ("frenetshipping_03298" → "03298"), or "".
     *
     * @param string $method
     * @return string
     */
    public static function serviceCode(string $method): string
    {
        return str_starts_with($method, 'frenetshipping_') ? substr($method, 15) : '';
    }

    /**
     * One order of the comparison.
     *
     * @param Order $order
     * @param bool $hasShipment
     * @return array
     */
    private function row(Order $order, bool $hasShipment): array
    {
        $address = $order->getShippingAddress();
        $package = $this->payload->package($order);
        $key = $this->invoices->get((int) $order->getId());
        $customerCode = self::serviceCode((string) $order->getShippingMethod());
        $row = [
            'id' => (int) $order->getId(),
            'number' => (string) $order->getIncrementId(),
            'customer' => $address ? trim($address->getFirstname() . ' ' . $address->getLastname()) : '',
            'email' => (string) $order->getCustomerEmail(),
            'phone' => $address ? (string) $address->getTelephone() : '',
            'document' => (string) ($order->getCustomerTaxvat() ?: ($address ? $address->getVatId() : '')),
            'postcode' => $address ? (string) $address->getPostcode() : '',
            'street' => $address ? implode(', ', array_filter((array) $address->getStreet(), 'strlen')) : '',
            'city' => $address ? trim($address->getCity() . ' / ' . $this->regionOf($address), ' /') : '',
            'items' => implode(', ', array_map(
                static fn ($item) => (int) $item->getQtyOrdered() . 'x ' . $item->getName(),
                $this->payload->shippableItems($order)
            )),
            'value' => (float) $order->getSubtotal(),
            'package' => $package,
            'nfe' => $key !== '' ? trim(chunk_split($key, 4, ' ')) : '',
            'customer_service' => (string) $order->getShippingDescription(),
            'customer_code' => $customerCode,
            'paid_by_customer' => (float) $order->getShippingAmount(),
            'services' => [],
            'selected' => null,
            'best_price' => null,
            'best_time' => null,
            'ok' => false,
            'reason' => '',
        ];
        if ($hasShipment) {
            $row['reason'] = (string) __('Already has a Frenet shipment.');
            return $row;
        }
        if (!$order->canShip()) {
            $row['reason'] = (string) __('The order cannot be shipped (already shipped, cancelled or on hold).');
            return $row;
        }
        if (!$address || $row['postcode'] === '') {
            $row['reason'] = (string) __('The order has no shipping address.');
            return $row;
        }
        if ($package['weight'] <= 0) {
            $row['reason'] = (string) __('The products have no weight: fill in the weight to quote.');
            return $row;
        }
        try {
            $services = $this->client->quoteAll(
                $this->config->storeValue('shipping/origin/postcode', (int) $order->getStoreId()),
                $row['postcode'],
                $row['value'],
                $this->payload->quoteItems($order)
            );
        } catch (LabelException $e) {
            $row['reason'] = (string) __('Frenet did not quote: %1', $e->getMessage());
            return $row;
        }
        if (!$services) {
            $row['reason'] = (string) __('No carrier delivers this package to this postcode.');
            return $row;
        }
        $row['services'] = $services;
        $row['best_price'] = Compare::bestPrice($services);
        $row['best_time'] = Compare::bestTime($services);
        $row['selected'] = Compare::preselect(
            $services,
            $customerCode,
            $this->config->defaultService(),
            $this->config->autoBest()
        );
        $row['ok'] = true;
        return $row;
    }

    /**
     * UF of an address.
     *
     * @param \Magento\Sales\Model\Order\Address $address
     * @return string
     */
    private function regionOf($address): string
    {
        return $this->regions->code(
            (int) $address->getRegionId(),
            (string) $address->getRegionCode(),
            (string) $address->getRegion()
        );
    }
}
