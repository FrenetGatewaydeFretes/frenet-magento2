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

use Frenet\Command\PostcodeInterface;
use Frenet\Command\ShippingInterface;
use Frenet\Command\TrackingInterface;

/**
 * Entry points into the frenet-php API used by the module: the postcode, tracking and shipping commands.
 */
interface ApiServiceInterface
{
    /**
     * Returns the Frenet postcode command.
     *
     * @return PostcodeInterface
     */
    public function postcode(): PostcodeInterface;

    /**
     * Returns the Frenet tracking command.
     *
     * @return TrackingInterface
     */
    public function tracking(): TrackingInterface;

    /**
     * Returns the Frenet shipping command.
     *
     * @return ShippingInterface
     */
    public function shipping(): ShippingInterface;
}
