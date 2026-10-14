<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

/**
 * Builds the body of POST /shipments and POST /orders from an order and the chosen service.
 * Rules the live API enforced on 2026-09-30 (not in the docs):
 * - Volumes is ONE object, not a list;
 * - Order.Created is ISO 8601 UTC with milliseconds;
 * - Quotation needs Carrier, CarrierCode, ShippingPrice and DeliveryTime (else HTTP 500 null reference);
 * - Order.From needs the full sender address even with UseFrenetRegistration.
 */
class PayloadBuilder
{
    /**
     * @param LabelsConfig $config
     * @param RegionResolver $regions
     * @param InvoiceStore $invoices
     */
    public function __construct(
        private readonly LabelsConfig $config,
        private readonly RegionResolver $regions,
        private readonly InvoiceStore $invoices
    ) {
    }

    /**
     * Physical items of the order (children of configurables/bundles, no virtual items).
     *
     * @param Order $order
     * @return Item[]
     */
    public function shippableItems(Order $order): array
    {
        $out = [];
        foreach ($order->getAllItems() as $item) {
            if ($item->getIsVirtual() || $item->getHasChildren() || $item->getProductType() === 'virtual') {
                continue;
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Unit weight in kg (store unit converted).
     *
     * @param Item $item
     * @return float
     */
    public function unitWeightKg(Item $item): float
    {
        $weight = (float) $item->getWeight();
        return $this->config->storeValue('general/locale/weight_unit') === 'lbs'
            ? round($weight * 0.45359237, 3)
            : $weight;
    }

    /**
     * Item dimensions in cm, from the attributes mapped in the carrier settings or their defaults.
     *
     * @param Item $item
     * @return array{length: float, height: float, width: float}
     */
    public function dimensions(Item $item): array
    {
        $product = $item->getProduct();
        $get = function (string $dim, float $default) use ($product): float {
            $code = $this->config->storeValue('carriers/frenetshipping/attributes_mapping/' . $dim . '_attribute')
                ?: 'volume_' . $dim;
            $value = $product ? (float) $product->getData($code) : 0.0;
            $fallback = (float) (
                $this->config->storeValue('carriers/frenetshipping/default_measurements/default_' . $dim) ?: $default
            );
            return $value > 0 ? $value : $fallback;
        };
        return ['length' => $get('length', 16.0), 'height' => $get('height', 2.0), 'width' => $get('width', 11.0)];
    }

    /**
     * ShippingItemArray for POST /shipping/quote.
     *
     * @param Order $order
     * @return array
     */
    public function quoteItems(Order $order): array
    {
        $out = [];
        foreach ($this->shippableItems($order) as $item) {
            $d = $this->dimensions($item);
            $out[] = [
                'SKU' => (string) $item->getSku(),
                'Quantity' => (int) $item->getQtyOrdered(),
                'Weight' => $this->unitWeightKg($item),
                'Length' => $d['length'],
                'Height' => $d['height'],
                'Width' => $d['width'],
            ];
        }
        return $out;
    }

    /**
     * Package summary of the order: total weight and the box (max length/width, stacked height).
     *
     * @param Order $order
     * @return array{weight: float, length: float, width: float, height: float}
     */
    public function package(Order $order): array
    {
        $weight = 0.0;
        $length = 0.0;
        $width = 0.0;
        $height = 0.0;
        foreach ($this->shippableItems($order) as $item) {
            $qty = (int) $item->getQtyOrdered();
            $d = $this->dimensions($item);
            $weight += $this->unitWeightKg($item) * $qty;
            $length = max($length, $d['length']);
            $width = max($width, $d['width']);
            $height += $d['height'] * $qty;
        }
        return ['weight' => round($weight, 3), 'length' => $length, 'width' => $width, 'height' => $height];
    }

    /**
     * Store information as the sender.
     *
     * @param int $storeId
     * @return array
     * @throws LocalizedException When the store address is incomplete.
     */
    public function sender(int $storeId): array
    {
        $info = fn (string $key): string => $this->config->storeValue('general/store_information/' . $key, $storeId);
        $address = AddressMapper::toFrenet(
            array_filter([$info('street_line1'), $info('street_line2')], 'strlen'),
            $info('city'),
            $this->regions->code((int) $info('region_id'), ''),
            $info('postcode')
        );
        if ($address['Street'] === '' || $address['City'] === '' || $address['ZipCode'] === ''
            || $address['AddressState'] === ''
        ) {
            throw new LocalizedException(__(
                'Fill in the store address (street, city, state and postcode) in Stores > Configuration > General > '
                . 'Store Information: Frenet requires the sender on every label. Nothing was charged.'
            ));
        }
        return [
            'Name' => $info('name') ?: 'Loja',
            'Phone' => $info('phone'),
            'Document' => (string) preg_replace('/\D/', '', $info('merchant_vat_number')),
            'Address' => $address,
        ];
    }

    /**
     * Body of one shipment.
     *
     * @param Order $order
     * @param array $service Chosen service: code, carrier, carrier_code, name, price, days.
     * @return array
     * @throws LocalizedException
     */
    public function build(Order $order, array $service): array
    {
        $shipping = $order->getShippingAddress();
        if (!$shipping) {
            throw new LocalizedException(__('The order has no shipping address.'));
        }
        $items = [];
        foreach ($this->shippableItems($order) as $item) {
            $d = $this->dimensions($item);
            $items[] = [
                'OrderId' => (string) $order->getIncrementId(),
                'ItemId' => (string) $item->getItemId(),
                'ProductId' => (string) $item->getProductId(),
                'ProductName' => (string) $item->getName(),
                'SKU' => (string) $item->getSku(),
                'Quantity' => (int) $item->getQtyOrdered(),
                'Price' => round((float) $item->getPrice(), 2),
                'Weight' => $this->unitWeightKg($item),
                'Length' => $d['length'],
                'Height' => $d['height'],
                'Width' => $d['width'],
                'IsFragile' => false,
            ];
        }
        $package = $this->package($order);
        $value = round((float) $order->getSubtotal(), 2);
        $created = strtotime((string) $order->getCreatedAt()) ?: time();
        $document = (string) ($order->getCustomerTaxvat() ?: $shipping->getVatId());
        $payload = [
            'Order' => [
                'Id' => (string) $order->getIncrementId(),
                'Value' => $value,
                'Created' => gmdate('Y-m-d\TH:i:s.000\Z', $created),
                'UseFrenetRegistration' => true,
                'Items' => $items,
                'From' => $this->sender((int) $order->getStoreId()),
                'To' => [
                    'Name' => trim($shipping->getFirstname() . ' ' . $shipping->getLastname()),
                    'Email' => (string) $order->getCustomerEmail(),
                    'Phone' => (string) $shipping->getTelephone(),
                    'Document' => (string) preg_replace('/\D/', '', $document),
                    'Address' => AddressMapper::toFrenet(
                        (array) $shipping->getStreet(),
                        (string) $shipping->getCity(),
                        $this->regions->code(
                            (int) $shipping->getRegionId(),
                            (string) $shipping->getRegionCode(),
                            (string) $shipping->getRegion()
                        ),
                        (string) $shipping->getPostcode()
                    ),
                ],
            ],
            'Volumes' => [
                'Weight' => $package['weight'],
                'Length' => $package['length'],
                'Height' => $package['height'],
                'Width' => $package['width'],
                'Price' => $value,
                'DeclaredValue' => $value,
                'OrderItemsId' => array_column($items, 'ItemId'),
            ],
            'Quotation' => [
                'ShippingServiceCode' => (string) $service['code'],
                'ShippingServiceName' => (string) ($service['name'] ?: $order->getShippingDescription()),
                'PlatformShippingPrice' => round((float) $order->getShippingAmount(), 2),
                'ShippingPrice' => round((float) $service['price'], 2),
                'DeliveryTime' => (int) $service['days'],
                'Carrier' => (string) $service['carrier'],
                'CarrierCode' => (string) $service['carrier_code'],
                'Services' => ['DeclaredValue' => false, 'ReceiptNotification' => false, 'OwnHand' => false],
            ],
        ];
        $invoice = $this->invoice($order);
        if ($invoice) {
            $payload['Order']['Invoice'] = $invoice;
        }
        return $payload;
    }

    /**
     * Order.Invoice from the NF-e key saved for the order (null when there is none).
     *
     * @param Order $order
     * @return array|null
     */
    public function invoice(Order $order): ?array
    {
        $key = $this->invoices->get((int) $order->getId());
        if ($key === '' || !NfeKey::isValid($key)) {
            return null;
        }
        $nf = NfeKey::parse($key);
        $created = strtotime((string) $order->getCreatedAt()) ?: time();
        return [
            'Key' => $key,
            'Number' => $nf['number'],
            'Series' => $nf['series'],
            'Value' => round((float) $order->getGrandTotal(), 2),
            'Date' => gmdate('Y-m-d\TH:i:s', $created),
        ];
    }
}
