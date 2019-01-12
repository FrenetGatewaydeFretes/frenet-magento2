<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

/**
 * Class Tracking
 * @package Frenet\Shipping\Model
 */
class Tracking implements TrackingInterface
{
    /**
     * @var ApiService
     */
    private $apiService;

    /**
     * Tracking constructor.
     * @param ApiService $apiService
     */
    public function __construct(
        ApiService $apiService
    ) {
        $this->apiService = $apiService;
    }

    /**
     * @inheritdoc
     */
    public function track($number, $shippingServiceCode)
    {
        /** @var \Frenet\Command\Tracking\TrackingInfoInterface $tracking */
        $tracking = $this->apiService
            ->tracking()
            ->trackingInfo()
            ->setShippingServiceCode($shippingServiceCode)
            ->setTrackingNumber($number);

        /** @var \Frenet\ObjectType\Entity\Tracking\TrackingInfoInterface $info */
        $info = $tracking->execute();

        return $info;
    }
}
