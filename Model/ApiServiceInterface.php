<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
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
 * Class ApiServiceInterface
 * @package Frenet\Shipping\Model
 */
interface ApiServiceInterface
{
    /**
     * @return \Frenet\Command\PostcodeInterface
     */
    public function postcode();

    /**
     * @return \Frenet\Command\TrackingInterface
     */
    public function tracking();

    /**
     * @return \Frenet\Command\ShippingInterface
     */
    public function shipping();
}
