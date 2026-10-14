<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Frenet\Shipping\Model\TrackingSync\InTransit;

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
     * @param InTransit $inTransit
     * @param ShipmentSender $trackSender
     */
    public function __construct(
        private readonly ShipOrderInterface $shipOrder,
        private readonly ShipmentTrackCreationInterfaceFactory $trackCreationFactory,
        private readonly TrackFactory $trackFactory,
        private readonly InTransit $inTransit,
        private readonly ShipmentSender $trackSender
    ) {
    }

    /**
     * Adds a Frenet tracking code to the order (false when it was already there or the order cannot take it).
     *
     * @param Order $order
     * @param string $number
     * @param bool $notify E-mail the shipment to the customer.
     * @return bool
     */
    public function addTracking(Order $order, string $number, bool $notify = false): bool
    {
        return in_array($this->attach($order, $number, $notify), [self::SHIPPED, self::ADDED], true);
    }

    /**
     * Ships the order with the code when it can still ship; otherwise adds the code to its last shipment.
     * Then moves the order to "Em transporte" when the automatic status is on.
     *
     * @param Order $order
     * @param string $number
     * @param bool $notify E-mail the shipment (or the new code) to the customer.
     * @return string self::SHIPPED, self::ADDED, self::EXISTS or self::NO_SHIPMENT
     */
    public function attach(Order $order, string $number, bool $notify = false): string
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
        $shipment = $order->getShipmentsCollection()->getLastItem();
        if (!$shipment || !$shipment->getId()) {
            return self::NO_SHIPMENT;
        }
        $track = $this->trackFactory->create()
            ->setCarrierCode('frenetshipping')
            ->setTitle($title)
            ->setNumber($number);
        $shipment->addTrack($track)->save();
        if ($notify) {
            try {
                $this->trackSender->send($shipment);
            } catch (\Throwable $e) {
                unset($e);
            }
        }
        $this->inTransit->apply((int) $order->getEntityId(), $number);
        return self::ADDED;
    }
}
