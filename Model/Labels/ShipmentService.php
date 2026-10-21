<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\Exception\LocalizedException;

/**
 * Frenet shipments of orders: one entry point for the admin screens and the cron, one class per step behind it
 * (ShipmentCreator, ShipmentPayer, LabelFetcher, ShipmentCanceller, LabelPrinter, RemoteStatus).
 *
 * Journeys: "here" creates the shipment (unpaid), pays it with the wallet and fetches the label; "panel" sends the
 * order to the Frenet panel, where it is paid and printed.
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
     * @param ShipmentCreator $creator
     * @param ShipmentPayer $payer
     * @param LabelFetcher $labels
     * @param ShipmentCanceller $canceller
     * @param LabelPrinter $printer
     * @param RemoteStatus $remote
     * @param LabelStore $store
     */
    public function __construct(
        private readonly ShipmentCreator $creator,
        private readonly ShipmentPayer $payer,
        private readonly LabelFetcher $labels,
        private readonly ShipmentCanceller $canceller,
        private readonly LabelPrinter $printer,
        private readonly RemoteStatus $remote,
        private readonly LabelStore $store
    ) {
    }

    /**
     * Creates the Frenet shipment of one order, unpaid (or sent to the Frenet panel).
     *
     * @param int $orderId
     * @param mixed[] $service code, carrier, carrier_code, name, price, days (from the comparison).
     * @param string $journey LabelsConfig::JOURNEY_HERE or JOURNEY_PANEL.
     * @return mixed[] Stored row.
     * @throws LocalizedException
     */
    public function create(int $orderId, array $service, string $journey): array
    {
        return $this->creator->create($orderId, $service, $journey);
    }

    /**
     * Creates the Frenet shipment and pays it at once with the wallet (journey "here").
     *
     * When Frenet does not charge it (no balance, payment without answer) the row stays unpaid and the reason is
     * kept on the order.
     *
     * @param int $orderId
     * @param mixed[] $service
     * @return mixed[] Stored row, with the status after the payment.
     * @throws LocalizedException
     */
    public function createAndPay(int $orderId, array $service): array
    {
        $row = $this->creator->create($orderId, $service, LabelsConfig::JOURNEY_HERE);
        $this->payer->pay([(string) $row['shipment_id']]);
        return (array) $this->store->get((string) $row['shipment_id']);
    }

    /**
     * Pays shipments with the wallet and reads the real status of each one.
     *
     * @param string[] $shipmentIds
     * @return array{paid: string[], unpaid: string[], message: string}
     */
    public function pay(array $shipmentIds): array
    {
        return $this->payer->pay($shipmentIds);
    }

    /**
     * Reads the label of a shipment; when it exists, ships the order with the tracking code.
     *
     * @param string $shipmentId
     * @return bool Label available.
     */
    public function fetchLabel(string $shipmentId): bool
    {
        return $this->labels->fetch($shipmentId);
    }

    /**
     * Cron: retries labels still missing.
     *
     * @return int Labels that became available.
     */
    public function poll(): int
    {
        return $this->labels->poll();
    }

    /**
     * Cancels (paid) or deletes (unpaid) a shipment.
     *
     * @param string $shipmentId
     * @return string Note for the admin.
     * @throws LabelException
     */
    public function cancel(string $shipmentId): string
    {
        return $this->canceller->cancel($shipmentId);
    }

    /**
     * One PDF with the labels of paid shipments.
     *
     * @param string[] $shipmentIds
     * @param string $format A4 or 10x15.
     * @return array{pdf: string, failed: int}
     * @throws LabelException
     */
    public function batchPdf(array $shipmentIds, string $format): array
    {
        return $this->printer->batchPdf($shipmentIds, $format);
    }

    /**
     * Current status read from Frenet; null when Frenet does not answer.
     *
     * @param string $shipmentId
     * @return int|null
     */
    public function realStatus(string $shipmentId): ?int
    {
        return $this->remote->get($shipmentId);
    }
}
