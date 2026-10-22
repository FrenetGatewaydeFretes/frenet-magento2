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
 * Cancels (paid) or deletes (unpaid) a Frenet shipment, deciding by its REAL status, and confirms it.
 *
 * Learned live on 2026-09-30: DELETE on a paid shipment answers OK and changes nothing, so paid ones go through
 * POST /{id}/cancel, which may answer an error and still schedule the cancellation.
 */
class ShipmentCanceller
{
    /**
     * @param Client $client
     * @param LabelStore $store
     * @param RemoteStatus $remote
     * @param Wallet $wallet
     * @param Shipper $shipper
     * @param IssueStore $issues
     * @param OrderComments $comments
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Client $client,
        private readonly LabelStore $store,
        private readonly RemoteStatus $remote,
        private readonly Wallet $wallet,
        private readonly Shipper $shipper,
        private readonly IssueStore $issues,
        private readonly OrderComments $comments,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cancels or deletes the shipment and updates the order.
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
        $status = $this->remote->get($shipmentId);
        if ($status === null) {
            throw new LabelException(
                __('Frenet did not answer the status of shipment %1. Nothing was changed; try again.', $shipmentId)
            );
        }
        if (!$this->remote->isGone($status)) {
            $status = $this->cancelRemote($shipmentId, $status);
        }
        $this->wallet->clear();
        $note = $this->noteFor($shipmentId, $status);
        $row = $this->store->get($shipmentId);
        if ($row) {
            $this->store->save($shipmentId, ['status' => $status]);
            $this->forget($row, $note);
        }
        return $note;
    }

    /**
     * Asks Frenet to cancel or delete the shipment and confirms with its real status afterwards.
     *
     * @param string $shipmentId
     * @param int $status Status before.
     * @return int Status after.
     * @throws LabelException When Frenet did not cancel it.
     */
    private function cancelRemote(string $shipmentId, int $status): int
    {
        $paid = !in_array($status, ShipmentService::UNPAID, true);
        try {
            $paid
                ? $this->client->whitelabel('POST', '/shipments/' . $shipmentId . '/cancel')
                : $this->client->whitelabel('DELETE', '/shipments/' . $shipmentId);
        } catch (LabelException $e) {
            $this->logger->warning('Frenet labels cancel ' . $shipmentId . ': ' . $e->getMessage());
        }
        $after = $this->remote->get($shipmentId);
        if (!$this->remote->isGone($after)) {
            throw new LabelException(
                __('Frenet did not cancel shipment %1. Cancel it in the Frenet panel.', $shipmentId)
            );
        }
        return (int) $after;
    }

    /**
     * What happened, for the admin and the order history.
     *
     * @param string $shipmentId
     * @param int $status
     * @return string
     */
    private function noteFor(string $shipmentId, int $status): string
    {
        $cancelled = [ShipmentService::STATUS_CANCEL_SCHEDULED, ShipmentService::STATUS_CANCELLED];
        return in_array($status, $cancelled, true)
            ? (string) __(
                'Frenet label %1 cancelled. Frenet returns the amount to the wallet (usually overnight).',
                $shipmentId
            )
            : (string) __('Unpaid Frenet shipment %1 deleted.', $shipmentId);
    }

    /**
     * The order forgets the label: its tracking code and the old note.
     *
     * Automatic tracking and the customer e-mails must stop following a label that will never move.
     *
     * @param mixed[] $row Stored shipment.
     * @param string $note
     * @return void
     */
    private function forget(array $row, string $note): void
    {
        $order = $this->comments->addToOrderId((int) $row['order_id'], $note);
        $trackingCode = (string) $row['tracking_code'];
        if ($trackingCode !== '' && $this->shipper->removeTracking($order, $trackingCode)) {
            $this->comments->add(
                $order,
                (string) __('Tracking code %1 removed: its Frenet label was cancelled.', $trackingCode)
            );
        }
        $this->issues->clear((int) $row['order_id']);
    }
}
