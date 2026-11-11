<?php
/**
 * Frenet Shipping Gateway — a Frenet tracking code added by hand to a shipment moves the order to
 * "Em transporte" (with the automatic status on). Codes added by the store's own flows apply it themselves.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Observer\TrackingSync;

use Frenet\Shipping\Model\TrackingSync\InTransit;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Shipment\Track;

class TrackAdded implements ObserverInterface
{
    /**
     * @param InTransit $inTransit
     */
    public function __construct(private readonly InTransit $inTransit)
    {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var Track|null $track */
        $track = $observer->getEvent()->getData('track');
        if ($track && $track->isObjectNew() && $track->getCarrierCode() === 'frenetshipping') {
            try {
                $this->inTransit->apply((int) $track->getOrderId(), (string) $track->getTrackNumber());
            } catch (\Throwable $e) {
                // Never block saving a shipment because of the status.
                unset($e);
            }
        }
    }
}
