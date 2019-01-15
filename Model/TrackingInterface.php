<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

/**
 * Class TrackingInterface
 * @package Frenet\Shipping\Model
 */
interface TrackingInterface
{
    /**
     * @param string $number
     * @param string $shippingServiceCode
     * @return \Frenet\ObjectType\Entity\Tracking\TrackingInfoInterface
     */
    public function track($number, $shippingServiceCode);
}
