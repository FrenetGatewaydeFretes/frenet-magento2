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

/**
 * Class TrackingInterface
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
