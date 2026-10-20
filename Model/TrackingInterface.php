<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Frenet\ObjectType\Entity\Tracking\TrackingInfoInterface;

/**
 * Looks up the tracking events of a shipment on the Frenet API.
 */
interface TrackingInterface
{
    /**
     * Returns the Frenet tracking information for the tracking number and the shipping service code.
     *
     * @param string $number
     * @param string $shippingServiceCode
     *
     * @return TrackingInfoInterface
     */
    public function track($number, $shippingServiceCode);
}
