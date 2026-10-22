<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Psr\Log\LoggerInterface;

/**
 * Step 2 of a label: paying shipments with the Frenet wallet (POST /shipments/checkout).
 *
 * The checkout answer is not reliable (learned live on 2026-09-30: it charged and did not say status 1), so the
 * real status of every shipment is read again and decides; the store never pays the same shipment twice.
 */
class ShipmentPayer
{
    /**
     * @param Client $client
     * @param LabelStore $store
     * @param Wallet $wallet
     * @param RemoteStatus $remote
     * @param LabelFetcher $labels
     * @param IssueStore $issues
     * @param OrderComments $comments
     * @param LoggerInterface $logger
     * @param ResponseReader $reader
     */
    public function __construct(
        private readonly Client $client,
        private readonly LabelStore $store,
        private readonly Wallet $wallet,
        private readonly RemoteStatus $remote,
        private readonly LabelFetcher $labels,
        private readonly IssueStore $issues,
        private readonly OrderComments $comments,
        private readonly LoggerInterface $logger,
        private readonly ResponseReader $reader
    ) {
    }

    /**
     * Pays the shipments and reads the real status of each one.
     *
     * @param string[] $shipmentIds
     * @return array{paid: string[], unpaid: string[], message: string}
     */
    public function pay(array $shipmentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $shipmentIds), 'ctype_digit')));
        $this->wallet->clear();
        $message = $this->checkout($ids);
        $paid = [];
        $unpaid = [];
        foreach ($ids as $shipmentId) {
            if ($this->settle($shipmentId)) {
                $paid[] = $shipmentId;
                continue;
            }
            $unpaid[] = $shipmentId;
        }
        if ($unpaid && $message === '') {
            $message = (string) __('Not enough balance in the Frenet wallet.');
        }
        // "Notes": the order keeps why its label was not paid, until it goes through.
        foreach ($unpaid as $shipmentId) {
            $row = $this->store->get($shipmentId);
            if ($row) {
                $this->issues->set((int) $row['order_id'], $message);
            }
        }
        return ['paid' => $paid, 'unpaid' => $unpaid, 'message' => $message];
    }

    /**
     * Sends the checkout; returns the error Frenet reported, or '' when it reported none.
     *
     * @param string[] $ids
     * @return string
     */
    private function checkout(array $ids): string
    {
        try {
            $checkout = $this->client->whitelabel('POST', '/shipments/checkout', array_map('intval', $ids));
        } catch (LabelException $e) {
            // Unknown result (e.g. timeout): the real status below and the cron decide; never pay twice.
            return (string) __(
                'The payment did not answer (%1). The store checks again every minute; do not pay twice.',
                $e->getMessage()
            );
        }
        $this->logger->info('Frenet labels checkout: ' . $this->reader->redact((string) json_encode($checkout)));
        $errors = (array) ($this->reader->field($checkout, 'errors') ?? []);
        if (!$errors) {
            return '';
        }
        return $this->reader->cleanError((string) ($this->reader->field((array) $errors[0], 'message') ?? ''));
    }

    /**
     * Reads the real status after the checkout, stores it and, when paid, comments the order and fetches the label.
     *
     * @param string $shipmentId
     * @return bool Paid.
     */
    private function settle(string $shipmentId): bool
    {
        $status = $this->remote->get($shipmentId) ?? ShipmentService::STATUS_PENDING_PAYMENT;
        $row = $this->store->get($shipmentId);
        if ($row) {
            $this->store->save($shipmentId, ['status' => $status]);
        }
        if (!$this->remote->isPaid($status)) {
            return false;
        }
        if ($row) {
            $this->issues->clear((int) $row['order_id']);
            $this->comments->addToOrderId((int) $row['order_id'], (string) __(
                'Frenet label %1 paid (R$ %2).',
                $shipmentId,
                number_format((float) $row['price'], 2, ',', '.')
            ));
            $this->labels->fetch($shipmentId);
        }
        return true;
    }
}
