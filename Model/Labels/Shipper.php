<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Frenet\Shipping\Model\TrackingSync\InTransit;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Throwable;

/**
 * Puts the Frenet tracking code on the order: ships it when it can still ship, otherwise adds the track to its
 * last shipment. The track carrier is "frenetshipping", so the existing Frenet tracking popup works.
 */
class Shipper
{
    public const SHIPPED = 'shipped';
    public const ADDED = 'added';
    public const EXISTS = 'exists';
    public const NO_SHIPMENT = 'no_shipment';

    /**
     * @param ShipOrderInterface $shipOrder
     * @param ShipmentTrackCreationInterfaceFactory $trackCreationFactory
     * @param TrackFactory $trackFactory
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param InTransit $inTransit
     * @param ShipmentSender $trackSender
     */
    public function __construct(
        private readonly ShipOrderInterface $shipOrder,
        private readonly ShipmentTrackCreationInterfaceFactory $trackCreationFactory,
        private readonly TrackFactory $trackFactory,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly InTransit $inTransit,
        private readonly ShipmentSender $trackSender
    ) {
    }

    /**
     * Adds a Frenet tracking code to the order, silently for the customer.
     *
     * @param Order $order
     * @param string $number
     * @return bool False when the code was already there or the order cannot take it.
     */
    public function addTracking(Order $order, string $number): bool
    {
        return $this->done($this->attach($order, $number));
    }

    /**
     * Adds a Frenet tracking code to the order and e-mails the shipment (or the new code) to the customer.
     *
     * @param Order $order
     * @param string $number
     * @return bool False when the code was already there or the order cannot take it.
     */
    public function addTrackingAndNotify(Order $order, string $number): bool
    {
        return $this->done($this->attachAndNotify($order, $number));
    }

    /**
     * Ships the order with the code when it can still ship; otherwise adds the code to its last shipment.
     *
     * Then moves the order to "Em transporte" when the automatic status is on. The customer is not e-mailed.
     *
     * @param Order $order
     * @param string $number
     * @return string self::SHIPPED, self::ADDED, self::EXISTS or self::NO_SHIPMENT
     */
    public function attach(Order $order, string $number): string
    {
        return $this->put($order, $number, false);
    }

    /**
     * Same as attach(), e-mailing the shipment (or the new code) to the customer.
     *
     * @param Order $order
     * @param string $number
     * @return string self::SHIPPED, self::ADDED, self::EXISTS or self::NO_SHIPMENT
     */
    public function attachAndNotify(Order $order, string $number): string
    {
        return $this->put($order, $number, true);
    }

    /**
     * Removes a tracking code from the shipments of the order (the Frenet label it came from was cancelled).
     *
     * @param Order $order
     * @param string $number
     * @return bool Removed.
     */
    public function removeTracking(Order $order, string $number): bool
    {
        $number = strtoupper(trim($number));
        $removed = false;
        foreach ($order->getTracksCollection() as $track) {
            if ($track->getCarrierCode() !== 'frenetshipping') {
                continue;
            }
            if (strtoupper((string) $track->getTrackNumber()) === $number) {
                $track->delete();
                $removed = true;
            }
        }
        return $removed;
    }

    /**
     * Whether an attach() result put the code on the order.
     *
     * @param string $result
     * @return bool
     */
    private function done(string $result): bool
    {
        return in_array($result, [self::SHIPPED, self::ADDED], true);
    }

    /**
     * The one implementation of attach(): the notification is the only difference.
     *
     * @param Order $order
     * @param string $number
     * @param bool $notify
     * @return string
     */
    private function put(Order $order, string $number, bool $notify): string
    {
        $number = strtoupper(trim($number));
        foreach ($order->getTracksCollection() as $existing) {
            if (strtoupper((string) $existing->getTrackNumber()) === $number) {
                return self::EXISTS;
            }
        }
        $title = (string) ($order->getShippingDescription() ?: 'Frenet');
        if ($order->canShip()) {
            $track = $this->trackCreationFactory->create();
            $track->setCarrierCode('frenetshipping');
            $track->setTitle($title);
            $track->setTrackNumber($number);
            $this->shipOrder->execute((int) $order->getEntityId(), [], $notify, false, null, [$track]);
            $this->inTransit->apply((int) $order->getEntityId(), $number);
            return self::SHIPPED;
        }
        /** @var Shipment $shipment */
        $shipment = $order->getShipmentsCollection()->getLastItem();
        if (!$shipment || !$shipment->getId()) {
            return self::NO_SHIPMENT;
        }
        /** @var Track $track */
        $track = $this->trackFactory->create()
            ->setCarrierCode('frenetshipping')
            ->setTitle($title)
            ->setNumber($number);
        $shipment->addTrack($track);
        $this->shipmentRepository->save($shipment);
        if ($notify) {
            try {
                $this->trackSender->send($shipment);
            } catch (Throwable $e) {
                unset($e);
            }
        }
        $this->inTransit->apply((int) $order->getEntityId(), $number);
        return self::ADDED;
    }
}
