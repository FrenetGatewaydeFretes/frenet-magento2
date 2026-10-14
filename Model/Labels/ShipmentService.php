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
use Psr\Log\LoggerInterface;

/**
 * Frenet shipments of orders, in two journeys:
 *  - "here":  POST /shipments (created unpaid, id saved at once) → POST /shipments/checkout (wallet) →
 *             GET /shipments/{id}/label (the cron retries every minute, up to POLL_MAX);
 *  - "panel": POST /orders — the shipment waits for payment and printing in the Frenet panel.
 * Learned live on 2026-09-30: the checkout answer is not reliable (it charged and did not say status 1), so the
 * real status always comes from GET /shipments/{id}; DELETE on a paid shipment answers OK and changes nothing, so
 * paid ones are cancelled with POST /{id}/cancel, which may answer an error and still schedule the cancellation.
 */
class ShipmentService
{
    public const STATUS_CREATED = 1;
    public const STATUS_PENDING_PAYMENT = 2;
    public const STATUS_PAYMENT_FAILED = 3;
    public const STATUS_PAID = 4;
    public const STATUS_POSTED = 5;
    public const STATUS_CANCEL_SCHEDULED = 6;
    public const STATUS_CANCELLED = 7;
    public const STATUS_DELETED = 9;
    public const STATUS_AT_DROP_OFF = 18;
    public const PAID = [self::STATUS_PAID, self::STATUS_POSTED, self::STATUS_AT_DROP_OFF];
    public const UNPAID = [self::STATUS_CREATED, self::STATUS_PENDING_PAYMENT, self::STATUS_PAYMENT_FAILED];
    public const POLL_MAX = 10;

    /**
     * @param Client $client
     * @param PayloadBuilder $payload
     * @param LabelStore $store
     * @param LabelsConfig $config
     * @param Wallet $wallet
     * @param Shipper $shipper
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Client $client,
        private readonly PayloadBuilder $payload,
        private readonly LabelStore $store,
        private readonly LabelsConfig $config,
        private readonly Wallet $wallet,
        private readonly Shipper $shipper,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Creates the Frenet shipment of one order with the chosen service.
     *
     * @param int $orderId
     * @param array $service code, carrier, carrier_code, name, price, days (from the comparison).
     * @param string $journey LabelsConfig::JOURNEY_HERE or JOURNEY_PANEL.
     * @param bool $pay Pay at once with the wallet (journey "here" only).
     * @return array Stored row.
     * @throws LocalizedException
     */
    public function create(int $orderId, array $service, string $journey, bool $pay): array
    {
        if (!$this->config->isAvailable()) {
            throw new LabelException(__('Frenet labels are off. Turn them on and add the partner token in the settings.'));
        }
        if ($this->store->activeForOrder($orderId)) {
            throw new LabelException(__('This order already has a Frenet shipment. Cancel it first.'));
        }
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);
        $panel = $journey === LabelsConfig::JOURNEY_PANEL;
        $res = $this->client->whitelabel('POST', $panel ? '/orders' : '/shipments', [$this->payload->build($order, $service)]);
        $item = (array) (Client::field((array) (Client::field($res, 'items') ?? []), 0) ?? []);
        $errors = (array) (Client::field($item, 'errors') ?? []);
        if ($errors) {
            throw new LabelException(__('%1', Client::cleanError((string) (Client::field((array) $errors[0], 'message') ?? ''))));
        }
        $shipmentId = (string) (Client::field($item, 'shipmentId') ?? '');
        if ($shipmentId === '') {
            throw new LabelException(__('Frenet did not return a shipment. Nothing was charged.'));
        }
        $this->store->save($shipmentId, [
            'order_id' => $orderId,
            'journey' => $panel ? LabelsConfig::JOURNEY_PANEL : LabelsConfig::JOURNEY_HERE,
            'status' => self::STATUS_PENDING_PAYMENT,
            'carrier_code' => (string) $service['carrier_code'],
            'service_code' => (string) $service['code'],
            'service_name' => trim($service['carrier'] . ' ' . $service['name']),
            'price' => round((float) $service['price'], 2),
            'poll_attempts' => 0,
        ]);
        $this->comment($order, $panel
            ? (string) __('Frenet shipment %1 sent to the Frenet panel, waiting for payment there.', $shipmentId)
            : (string) __('Frenet shipment %1 created, not paid yet.', $shipmentId));
        if (!$panel && $pay) {
            $this->pay([$shipmentId]);
        }
        return (array) $this->store->get($shipmentId);
    }

    /**
     * Pays shipments with the wallet (POST /shipments/checkout) and reads the real status of each one.
     *
     * @param string[] $shipmentIds
     * @return array{paid: string[], unpaid: string[], message: string}
     * @throws LabelException When Frenet does not answer at all.
     */
    public function pay(array $shipmentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $shipmentIds), 'ctype_digit')));
        $this->wallet->clear();
        $message = '';
        try {
            $checkout = $this->client->whitelabel('POST', '/shipments/checkout', array_map('intval', $ids));
            $this->logger->info('Frenet labels checkout: ' . Client::redact((string) json_encode($checkout)));
            $errors = (array) (Client::field($checkout, 'errors') ?? []);
            if ($errors) {
                $message = Client::cleanError((string) (Client::field((array) $errors[0], 'message') ?? ''));
            }
        } catch (LabelException $e) {
            // Unknown result (e.g. timeout): the real status below and the cron decide; never pay twice.
            $message = (string) __('The payment did not answer (%1). The store checks again every minute; do not pay twice.', $e->getMessage());
        }
        $paid = [];
        $unpaid = [];
        foreach ($ids as $id) {
            $status = $this->realStatus($id) ?? self::STATUS_PENDING_PAYMENT;
            $row = $this->store->get($id);
            if ($row) {
                $this->store->save($id, ['status' => $status]);
            }
            if (in_array($status, self::PAID, true)) {
                $paid[] = $id;
                if ($row) {
                    $this->comment($this->orderRepository->get((int) $row['order_id']), (string) __(
                        'Frenet label %1 paid (R$ %2).',
                        $id,
                        number_format((float) $row['price'], 2, ',', '.')
                    ));
                    $this->fetchLabel($id);
                }
            } else {
                $unpaid[] = $id;
            }
        }
        if ($unpaid && $message === '') {
            $message = (string) __('Not enough balance in the Frenet wallet.');
        }
        return ['paid' => $paid, 'unpaid' => $unpaid, 'message' => $message];
    }

    /**
     * GET /shipments/{id}/label; when the label exists, stores the URLs and ships the order with the tracking code.
     *
     * @param string $shipmentId
     * @return bool Label available.
     */
    public function fetchLabel(string $shipmentId): bool
    {
        $row = $this->store->get($shipmentId);
        if (!$row) {
            return false;
        }
        $this->store->save($shipmentId, ['poll_attempts' => (int) $row['poll_attempts'] + 1]);
        try {
            $res = $this->client->whitelabel('GET', '/shipments/' . rawurlencode($shipmentId) . '/label');
        } catch (LabelException $e) {
            $this->logger->warning('Frenet label ' . $shipmentId . ': ' . $e->getMessage());
            return false;
        }
        $label = (string) (Client::field($res, 'labelUrl') ?? '');
        $trackingUrl = (string) (Client::field($res, 'trackingUrl') ?? '');
        $code = preg_match('#/([A-Za-z0-9]{8,})/?$#', $trackingUrl, $m) ? $m[1] : '';
        $data = [
            'label_url' => $label,
            'declaration_url' => (string) (Client::field($res, 'declarationUrl') ?? ''),
            'tracking_url' => $trackingUrl,
            'tracking_code' => $code,
        ];
        $status = Client::field($res, 'shipmentStatus');
        if (is_numeric($status)) {
            $data['status'] = (int) $status;
        }
        $this->store->save($shipmentId, $data);
        if ($label === '') {
            return false;
        }
        /** @var Order $order */
        $order = $this->orderRepository->get((int) $row['order_id']);
        $this->comment($order, (string) __('Frenet label %1 ready to print.', $shipmentId));
        if ($code !== '') {
            try {
                $this->shipper->addTracking($order, $code);
            } catch (\Throwable $e) {
                $this->logger->error('Frenet labels: tracking ' . $code . ' on order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            }
        }
        return true;
    }

    /**
     * Cron: retries labels still missing.
     *
     * @return int Labels that became available.
     */
    public function poll(): int
    {
        $done = 0;
        foreach ($this->store->waitingForLabel(self::POLL_MAX) as $row) {
            $id = (string) $row['shipment_id'];
            if ((int) $row['status'] === self::STATUS_PENDING_PAYMENT) {
                // A payment that did not answer: only continue when Frenet really charged it.
                $status = $this->realStatus($id);
                if ($status === null || !in_array($status, self::PAID, true)) {
                    $this->store->save($id, ['poll_attempts' => (int) $row['poll_attempts'] + 1]);
                    continue;
                }
                $this->store->save($id, ['status' => $status]);
            }
            $done += (int) $this->fetchLabel($id);
        }
        return $done;
    }

    /**
     * Cancels (paid) or deletes (unpaid) a Frenet shipment, deciding by its REAL status, and confirms it.
     *
     * @param string $shipmentId
     * @return string Note for the admin.
     * @throws LabelException
     */
    public function cancel(string $shipmentId): string
    {
        if (!ctype_digit($shipmentId)) {
            throw new LabelException(__('Invalid Frenet shipment.'));
        }
        $status = $this->realStatus($shipmentId);
        $row = $this->store->get($shipmentId);
        if ($status === null) {
            throw new LabelException(__('Frenet did not answer the status of shipment %1. Nothing was changed; try again.', $shipmentId));
        }
        if (!in_array($status, LabelStore::GONE, true)) {
            $paid = !in_array($status, self::UNPAID, true);
            try {
                $paid
                    ? $this->client->whitelabel('POST', '/shipments/' . $shipmentId . '/cancel')
                    : $this->client->whitelabel('DELETE', '/shipments/' . $shipmentId);
            } catch (LabelException $e) {
                $this->logger->warning('Frenet labels cancel ' . $shipmentId . ': ' . $e->getMessage());
            }
            $after = $this->realStatus($shipmentId);
            if ($after === null || !in_array($after, LabelStore::GONE, true)) {
                throw new LabelException(__('Frenet did not cancel shipment %1. Cancel it in the Frenet panel.', $shipmentId));
            }
            $status = $after;
        }
        $this->wallet->clear();
        $note = in_array($status, [self::STATUS_CANCEL_SCHEDULED, self::STATUS_CANCELLED], true)
            ? (string) __('Frenet label %1 cancelled. Frenet returns the amount to the wallet (usually overnight).', $shipmentId)
            : (string) __('Unpaid Frenet shipment %1 deleted.', $shipmentId);
        if ($row) {
            $this->store->save($shipmentId, ['status' => $status]);
            $this->comment($this->orderRepository->get((int) $row['order_id']), $note);
        }
        return $note;
    }

    /**
     * One PDF with the labels of paid shipments (POST /shipments/batch/label/generate).
     *
     * @param string[] $shipmentIds
     * @param string $format A4 or 10x15.
     * @return array{pdf: string, failed: int}
     * @throws LabelException
     */
    public function batchPdf(array $shipmentIds, string $format): array
    {
        $ids = array_map('intval', array_values(array_filter(array_map('strval', $shipmentIds), 'ctype_digit')));
        if (!$ids) {
            throw new LabelException(__('Select at least one paid label.'));
        }
        $res = $this->client->whitelabel('POST', '/shipments/batch/label/generate', $ids, $format);
        $document = (string) (Client::field($res, 'document') ?? '');
        // Frenet returns the PDF as base64 in "document" (POST /shipments/batch/label/generate).
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $pdf = $document !== '' ? (string) base64_decode($document, true) : '';
        if ($pdf === '') {
            throw new LabelException(__('Frenet did not generate the PDF. Only paid labels can be printed.'));
        }
        // Frenet repeats the item of a label it cannot print, so failures are counted per shipment.
        $failed = [];
        foreach ((array) (Client::field($res, 'items') ?? []) as $item) {
            if (!empty(Client::field((array) $item, 'errors'))) {
                $failed[(string) Client::field((array) $item, 'shipmentId')] = true;
            }
        }
        return ['pdf' => $pdf, 'failed' => count($failed)];
    }

    /**
     * Current status read from Frenet (GET /shipments/{id}); null when Frenet does not answer.
     *
     * @param string $shipmentId
     * @return int|null
     */
    public function realStatus(string $shipmentId): ?int
    {
        try {
            $res = $this->client->whitelabel('GET', '/shipments/' . rawurlencode($shipmentId));
        } catch (LabelException $e) {
            $this->logger->warning('Frenet labels status ' . $shipmentId . ': ' . $e->getMessage());
            return null;
        }
        $status = Client::field($res, 'shipmentStatus');
        return is_numeric($status) ? (int) $status : null;
    }

    /**
     * Adds an admin comment to the order and saves it.
     *
     * @param Order|\Magento\Sales\Api\Data\OrderInterface $order
     * @param string $text
     * @return void
     */
    private function comment($order, string $text): void
    {
        $order->addCommentToStatusHistory($text);
        $this->orderRepository->save($order);
    }
}
