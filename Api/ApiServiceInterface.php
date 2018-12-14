<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Api;

/**
 * Class ApiServiceInterface
 * @package Frenet\Shipping\Api
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
