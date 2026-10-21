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
 * Status of a shipment as Frenet has it (GET /shipments/{id}): the only reliable one. Learned live on 2026-09-30:
 * the checkout answer charged and did not say status 1, so every decision reads the status again.
 */
class RemoteStatus
{
    /**
     * @param Client $client
     * @param LoggerInterface $logger
     * @param ResponseReader $reader
     */
    public function __construct(
        private readonly Client $client,
        private readonly LoggerInterface $logger,
        private readonly ResponseReader $reader
    ) {
    }

    /**
     * Current status, or null when Frenet does not answer.
     *
     * @param string $shipmentId
     * @return int|null
     */
    public function get(string $shipmentId): ?int
    {
        try {
            $res = $this->client->whitelabel('GET', '/shipments/' . rawurlencode($shipmentId));
        } catch (LabelException $e) {
            $this->logger->warning('Frenet labels status ' . $shipmentId . ': ' . $e->getMessage());
            return null;
        }
        $status = $this->reader->field($res, 'shipmentStatus');
        return is_numeric($status) ? (int) $status : null;
    }

    /**
     * Whether the status means the label is paid (paid, posted or at the drop-off point).
     *
     * @param int|null $status
     * @return bool
     */
    public function isPaid(?int $status): bool
    {
        return $status !== null && in_array($status, ShipmentService::PAID, true);
    }

    /**
     * Whether the status means the shipment no longer exists (cancelled, cancellation scheduled or deleted).
     *
     * @param int|null $status
     * @return bool
     */
    public function isGone(?int $status): bool
    {
        return $status !== null && in_array($status, LabelStore::GONE, true);
    }
}
