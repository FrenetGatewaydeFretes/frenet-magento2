<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Step 1 of a label: the Frenet shipment of an order, unpaid.
 *
 * Journey "here": POST /shipments, the id is saved at once and the payment comes apart (ShipmentPayer).
 * Journey "panel": POST /orders, the shipment waits for payment and printing in the Frenet panel.
 */
class ShipmentCreator
{
    /**
     * @param Client $client
     * @param PayloadBuilder $payload
     * @param LabelStore $store
     * @param LabelsConfig $config
     * @param OrderRepositoryInterface $orderRepository
     * @param NfeCarriers $nfeCarriers
     * @param IssueStore $issues
     * @param OrderComments $comments
     * @param ResponseReader $reader
     */
    public function __construct(
        private readonly Client $client,
        private readonly PayloadBuilder $payload,
        private readonly LabelStore $store,
        private readonly LabelsConfig $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly NfeCarriers $nfeCarriers,
        private readonly IssueStore $issues,
        private readonly OrderComments $comments,
        private readonly ResponseReader $reader
    ) {
    }

    /**
     * Creates the Frenet shipment of one order with the chosen service and stores it.
     *
     * On failure the reason stays on the order ("Notes") until a label goes through.
     *
     * @param int $orderId
     * @param mixed[] $service code, carrier, carrier_code, name, price, days (from the comparison).
     * @param string $journey LabelsConfig::JOURNEY_HERE or JOURNEY_PANEL.
     * @return mixed[] Stored row.
     * @throws LocalizedException
     */
    public function create(int $orderId, array $service, string $journey): array
    {
        if (!$this->config->isAvailable()) {
            throw new LabelException(
                __('Frenet labels are off. Turn them on and add the partner token in the settings.')
            );
        }
        if ($this->store->activeForOrder($orderId)) {
            throw new ShipmentExistsException(__('This order already has a Frenet shipment. Cancel it first.'));
        }
        try {
            $shipmentId = $this->send($orderId, $service, $journey);
        } catch (LocalizedException $e) {
            $this->issues->set($orderId, $e->getMessage());
            throw $e;
        }
        $this->issues->clear($orderId);
        return (array) $this->store->get($shipmentId);
    }

    /**
     * Sends the shipment to Frenet, checks its answer and stores the new row.
     *
     * @param int $orderId
     * @param mixed[] $service
     * @param string $journey
     * @return string Shipment id.
     * @throws LocalizedException
     */
    private function send(int $orderId, array $service, string $journey): string
    {
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);
        $panel = $journey === LabelsConfig::JOURNEY_PANEL;
        // Later attempts for the same order go as "000000123-2", "000000123-3" (see FrenetOrderId).
        $attempt = $this->store->countForOrder($orderId) + 1;
        $res = $this->client->whitelabel(
            'POST',
            $panel ? '/orders' : '/shipments',
            [$this->payload->build($order, $service, $attempt)]
        );
        $item = (array) ($this->reader->field((array) ($this->reader->field($res, 'items') ?? []), 0) ?? []);
        $errors = (array) ($this->reader->field($item, 'errors') ?? []);
        if ($errors) {
            $message = (string) ($this->reader->field((array) $errors[0], 'message') ?? '');
            throw $this->refusal($orderId, $service, $message);
        }
        $shipmentId = (string) ($this->reader->field($item, 'shipmentId') ?? '');
        if ($shipmentId === '') {
            throw new LabelException(__('Frenet did not return a shipment. Nothing was charged.'));
        }
        $this->store->save($shipmentId, [
            'order_id' => $orderId,
            'journey' => $panel ? LabelsConfig::JOURNEY_PANEL : LabelsConfig::JOURNEY_HERE,
            'status' => ShipmentService::STATUS_PENDING_PAYMENT,
            'carrier_code' => (string) $service['carrier_code'],
            'service_code' => (string) $service['code'],
            'service_name' => trim($service['carrier'] . ' ' . $service['name']),
            'price' => round((float) $service['price'], 2),
            'poll_attempts' => 0,
        ]);
        $this->comments->add($order, $panel
            ? (string) __('Frenet shipment %1 sent to the Frenet panel, waiting for payment there.', $shipmentId)
            : (string) __('Frenet shipment %1 created, not paid yet.', $shipmentId));
        return $shipmentId;
    }

    /**
     * The exception for a shipment Frenet refused.
     *
     * An invoice refusal teaches "Create shipment" that the carrier needs an NF-e key, so it stops proposing that
     * carrier to orders without one.
     *
     * @param int $orderId
     * @param mixed[] $service
     * @param string $raw Frenet message.
     * @return LabelException
     */
    private function refusal(int $orderId, array $service, string $raw): LabelException
    {
        if (!$this->nfeCarriers->isInvoiceError($raw)) {
            return new LabelException(__('%1', $this->reader->cleanError($raw)));
        }
        if ($this->payload->invoiceKey($orderId) !== '') {
            return new LabelException(__(
                'Frenet did not accept the NF-e key of this order. '
                . 'Check that it is the invoice of this order. Nothing was charged.'
            ));
        }
        $this->nfeCarriers->remember((string) $service['carrier_code']);
        return new LabelException(__(
            'This carrier requires an invoice. Paste the NF-e key of the order and try again, '
            . 'or choose another carrier. Nothing was charged.'
        ));
    }
}
