<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Frenet\Shipping\Model\Extras\Settings as ExtrasSettings;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Step 3 of a label: the PDF and tracking code of a paid shipment (GET /shipments/{id}/label), and the cron that
 * retries the ones still missing, up to POLL_MAX times.
 */
class LabelFetcher
{
    /**
     * @param Client $client
     * @param LabelStore $store
     * @param RemoteStatus $remote
     * @param Shipper $shipper
     * @param IssueStore $issues
     * @param OrderComments $comments
     * @param ExtrasSettings $extras
     * @param LoggerInterface $logger
     * @param ResponseReader $reader
     */
    public function __construct(
        private readonly Client $client,
        private readonly LabelStore $store,
        private readonly RemoteStatus $remote,
        private readonly Shipper $shipper,
        private readonly IssueStore $issues,
        private readonly OrderComments $comments,
        private readonly ExtrasSettings $extras,
        private readonly LoggerInterface $logger,
        private readonly ResponseReader $reader
    ) {
    }

    /**
     * Reads the label; when it exists, stores the URLs and ships the order with the tracking code.
     *
     * @param string $shipmentId
     * @return bool Label available.
     */
    public function fetch(string $shipmentId): bool
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
        $label = (string) ($this->reader->field($res, 'labelUrl') ?? '');
        $trackingUrl = (string) ($this->reader->field($res, 'trackingUrl') ?? '');
        $code = preg_match('#/([A-Za-z0-9]{8,})/?$#', $trackingUrl, $matches) ? $matches[1] : '';
        $data = [
            'label_url' => $label,
            'declaration_url' => (string) ($this->reader->field($res, 'declarationUrl') ?? ''),
            'tracking_url' => $trackingUrl,
            'tracking_code' => $code,
        ];
        $status = $this->reader->field($res, 'shipmentStatus');
        if (is_numeric($status)) {
            $data['status'] = (int) $status;
        }
        $this->store->save($shipmentId, $data);
        if ($label === '') {
            return false;
        }
        $order = $this->comments->addToOrderId(
            (int) $row['order_id'],
            (string) __('Frenet label %1 ready to print.', $shipmentId)
        );
        // Paid after all (payment without answer): the Notes column has nothing to say any more.
        $this->issues->clear((int) $row['order_id']);
        if ($code !== '') {
            try {
                // Tells the customer the code, like a code typed in the order, when the tracking e-mails are on.
                $this->extras->notifyCustomer()
                    ? $this->shipper->addTrackingAndNotify($order, $code)
                    : $this->shipper->addTracking($order, $code);
            } catch (Throwable $e) {
                $this->logger->error(
                    'Frenet labels: tracking ' . $code . ' on order ' . $order->getIncrementId() . ': '
                    . $e->getMessage()
                );
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
        foreach ($this->store->waitingForLabel(ShipmentService::POLL_MAX) as $row) {
            $shipmentId = (string) $row['shipment_id'];
            if ((int) $row['status'] === ShipmentService::STATUS_PENDING_PAYMENT) {
                // A payment that did not answer: only continue when Frenet really charged it.
                $status = $this->remote->get($shipmentId);
                if (!$this->remote->isPaid($status)) {
                    $this->store->save($shipmentId, ['poll_attempts' => (int) $row['poll_attempts'] + 1]);
                    continue;
                }
                $this->store->save($shipmentId, ['status' => $status]);
            }
            $done += (int) $this->fetch($shipmentId);
        }
        return $done;
    }
}
