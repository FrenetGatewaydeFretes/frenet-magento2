<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
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
     * @param Client $client
     * @param PayloadBuilder $payload
     * @param LabelsConfig $config
     * @param RegionResolver $regions
     * @param NfeCarriers $nfeCarriers
     * @param IssueStore $issues
     * @param Compare $compare
     */
    public function __construct(
        private readonly CollectionFactory $orders,
        private readonly LabelStore $store,
        private readonly Client $client,
        private readonly PayloadBuilder $payload,
        private readonly LabelsConfig $config,
        private readonly RegionResolver $regions,
        private readonly NfeCarriers $nfeCarriers,
        private readonly IssueStore $issues,
        private readonly Compare $compare
    ) {
    }

    /**
     * Recent orders still waiting for a Frenet shipment (not shipped, not cancelled, no shipment yet).
     *
     * @param string $search Order number or customer name.
     * @param int $limit
     * @return array<int, mixed[]>
     */
    public function candidates(string $search = '', int $limit = 50): array
    {
        // Orders whose Frenet label was cancelled may already be shipped (even complete) in Magento: list them too.
        $relabel = $this->store->onlyGone();
        $collection = $this->orders->create()
            ->addFieldToFilter(
                ['state', 'entity_id'],
                [['in' => [Order::STATE_NEW, Order::STATE_PROCESSING]], ['in' => $relabel ?: [0]]]
            )
            ->addFieldToFilter('is_virtual', '0')
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
        $issues = $this->issues->forOrders($ids);
        $out = [];
        foreach ($collection as $order) {
            /** @var Order $order */
            if (isset($taken[(int) $order->getId()])
                || !$this->canTakeLabel($order, in_array((int) $order->getId(), $relabel, true))
            ) {
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
                'frenet' => $this->serviceCode((string) $order->getShippingMethod()) !== '',
                'total' => (float) $order->getGrandTotal(),
                'issue' => $issues[(int) $order->getId()] ?? null,
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
     * @return array{rows: mixed[], header: mixed[], truncated: bool}
     */
    public function review(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        $truncated = count($orderIds) > self::MAX_ORDERS;
        $orderIds = array_slice($orderIds, 0, self::MAX_ORDERS);
        $rows = [];
        if ($orderIds) {
            $taken = array_flip($this->store->ordersWithActive($orderIds));
            $issues = $this->issues->forOrders($orderIds);
            $nfeCarriers = $this->nfeCarriers->all();
            $collection = $this->orders->create()->addFieldToFilter('entity_id', ['in' => $orderIds]);
            foreach ($collection as $order) {
                /** @var Order $order */
                $row = $this->row($order, isset($taken[(int) $order->getId()]), $nfeCarriers);
                $row['issue'] = $issues[(int) $order->getId()] ?? null;
                $rows[] = $row;
            }
        }
        $quoted = array_values(array_filter($rows, static fn ($row) => $row['ok']));
        return [
            'rows' => $rows,
            'header' => $this->compare->carriers(array_column($quoted, 'services'), array_column($quoted, 'usable')),
            'truncated' => $truncated,
        ];
    }

    /**
     * Whether the order can get a Frenet label.
     *
     * It can when it still ships, or when Magento shipped it with a Frenet label that was cancelled afterwards (the
     * new label's tracking code goes to that shipment).
     *
     * @param Order $order
     * @param bool $hadLabel The order had Frenet labels, all cancelled or deleted.
     * @return bool
     */
    private function canTakeLabel(Order $order, bool $hadLabel): bool
    {
        if ($order->canShip()) {
            return true;
        }
        return $hadLabel && !$order->isCanceled()
            && !in_array($order->getState(), [Order::STATE_CLOSED, Order::STATE_CANCELED, Order::STATE_HOLDED], true);
    }

    /**
     * Frenet service code of a shipping method ("frenetshipping_03298" → "03298"), or "".
     *
     * @param string $method
     * @return string
     */
    private function serviceCode(string $method): string
    {
        return str_starts_with($method, 'frenetshipping_') ? substr($method, 15) : '';
    }

    /**
     * One order of the comparison: its data, then why it cannot be quoted or its services and the preselected one.
     *
     * @param Order $order
     * @param bool $hasShipment
     * @param string[] $nfeCarriers Carriers that refuse an order without an NF-e key.
     * @return mixed[]
     */
    private function row(Order $order, bool $hasShipment, array $nfeCarriers): array
    {
        $address = $order->getShippingAddress();
        $package = $this->payload->package($order);
        $row = $this->baseRow($order, $address, $package);
        $reason = $this->blockedReason($order, $hasShipment, $address, $package);
        if ($reason !== null) {
            $row['reason'] = $reason;
            return $row;
        }
        $services = $this->quote($order, $row);
        if (is_string($services)) {
            $row['reason'] = $services;
            return $row;
        }
        return $this->withServices($row, $this->compare->markNfe($services, $nfeCarriers));
    }

    /**
     * The order as the comparison table shows it, before quoting.
     *
     * @param Order $order
     * @param Address|null $address
     * @param mixed[] $package
     * @return mixed[]
     */
    private function baseRow(Order $order, ?Address $address, array $package): array
    {
        $key = $this->payload->invoiceKey((int) $order->getId());
        $recipient = $this->recipient($address);
        return [
            'id' => (int) $order->getId(),
            'number' => (string) $order->getIncrementId(),
            'customer' => $recipient['customer'],
            'email' => (string) $order->getCustomerEmail(),
            'phone' => $recipient['phone'],
            'document' => (string) ($order->getCustomerTaxvat() ?: $recipient['vat']),
            'postcode' => $recipient['postcode'],
            'street' => $recipient['street'],
            'city' => $recipient['city'],
            'items' => implode(', ', array_map(
                static fn ($item) => (int) $item->getQtyOrdered() . 'x ' . $item->getName(),
                $this->payload->shippableItems($order)
            )),
            'value' => (float) $order->getSubtotal(),
            'package' => $package,
            'nfe' => $key !== '' ? trim(chunk_split($key, 4, ' ')) : '',
            'has_nfe' => $this->payload->validInvoiceKey($key),
            'customer_service' => (string) $order->getShippingDescription(),
            'customer_code' => $this->serviceCode((string) $order->getShippingMethod()),
            'paid_by_customer' => (float) $order->getShippingAmount(),
            'services' => [],
            'usable' => [],
            'selected' => null,
            'best_price' => null,
            'best_time' => null,
            'ok' => false,
            'reason' => '',
        ];
    }

    /**
     * Recipient fields of a row; empty when the order has no shipping address.
     *
     * @param Address|null $address
     * @return array{customer: string, phone: string, postcode: string, street: string, city: string, vat: string}
     */
    private function recipient(?Address $address): array
    {
        if (!$address) {
            return ['customer' => '', 'phone' => '', 'postcode' => '', 'street' => '', 'city' => '', 'vat' => ''];
        }
        return [
            'customer' => trim($address->getFirstname() . ' ' . $address->getLastname()),
            'phone' => (string) $address->getTelephone(),
            'postcode' => (string) $address->getPostcode(),
            'street' => implode(
                ', ',
                array_filter((array) $address->getStreet(), fn ($value): bool => (string) $value !== '')
            ),
            'city' => trim($address->getCity() . ' / ' . $this->regionOf($address), ' /'),
            'vat' => (string) $address->getVatId(),
        ];
    }

    /**
     * Why the order cannot be quoted, in order of the checks; null when it can.
     *
     * @param Order $order
     * @param bool $hasShipment
     * @param Address|null $address
     * @param mixed[] $package
     * @return string|null
     */
    private function blockedReason(Order $order, bool $hasShipment, ?Address $address, array $package): ?string
    {
        if ($hasShipment) {
            return (string) __('Already has a Frenet shipment.');
        }
        if (!$this->canTakeLabel($order, $this->store->countForOrder((int) $order->getId()) > 0)) {
            return (string) __('The order cannot be shipped (already shipped, cancelled or on hold).');
        }
        if (!$address || (string) $address->getPostcode() === '') {
            return (string) __('The order has no shipping address.');
        }
        if ($package['weight'] <= 0) {
            return (string) __('The products have no weight: fill in the weight to quote.');
        }
        return null;
    }

    /**
     * Every Frenet service for the order, or the reason why there is none.
     *
     * @param Order $order
     * @param mixed[] $row
     * @return mixed[]|string
     */
    private function quote(Order $order, array $row)
    {
        try {
            $services = $this->client->quoteAll(
                $this->config->storeValue('shipping/origin/postcode', (int) $order->getStoreId()),
                $row['postcode'],
                $row['value'],
                $this->payload->quoteItems($order)
            );
        } catch (LabelException $e) {
            return (string) __('Frenet did not quote: %1', $e->getMessage());
        }
        return $services ?: (string) __('No carrier delivers this package to this postcode.');
    }

    /**
     * The row with its services: the usable ones (NF-e rule), the best of each kind and the preselected one.
     *
     * @param mixed[] $row
     * @param mixed[] $services markNfe() result.
     * @return mixed[]
     */
    private function withServices(array $row, array $services): array
    {
        // Without an NF-e key, never propose by default a carrier that Frenet refuses without one.
        $usable = $row['has_nfe'] ? $services : $this->compare->usableWithoutNfe($services);
        $cheapest = $this->compare->bestPrice($usable);
        $fastest = $this->compare->bestTime($usable);
        $pick = $this->compare->preselect(
            $usable,
            $row['customer_code'],
            $this->config->defaultService(),
            $this->config->autoBest()
        );
        $row['services'] = $services;
        $row['usable'] = $usable;
        $row['best_price'] = $this->compare->indexOf($services, $usable[$cheapest]['code'] ?? null);
        $row['best_time'] = $this->compare->indexOf($services, $usable[$fastest]['code'] ?? null);
        $row['selected'] = $this->compare->indexOf($services, $pick === null ? null : (string) $usable[$pick]['code']);
        $row['ok'] = true;
        return $row;
    }

    /**
     * UF of an address.
     *
     * @param Address $address
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
