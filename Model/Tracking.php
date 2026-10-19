<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model;

use Frenet\Command\Tracking\TrackingInfoInterface as TrackingInfoCommand;
use Frenet\ObjectType\Entity\Tracking\TrackingInfoInterface;

/**
 * Fetches the tracking events of a shipment from the Frenet tracking API.
 */
class Tracking implements TrackingInterface
{
    /**
     * @var ApiService
     */
    private $apiService;

    /**
     * @param ApiService $apiService
     */
    public function __construct(
        ApiService $apiService
    ) {
        $this->apiService = $apiService;
    }

    /**
     * @inheritDoc
     */
    public function track($number, $shippingServiceCode)
    {
        /** @var TrackingInfoCommand $tracking */
        $tracking = $this->apiService
            ->tracking()
            ->trackingInfo()
            ->setShippingServiceCode($shippingServiceCode)
            ->setTrackingNumber($number);

        /** @var TrackingInfoInterface $info */
        $info = $tracking->execute();

        return $info;
    }
}
