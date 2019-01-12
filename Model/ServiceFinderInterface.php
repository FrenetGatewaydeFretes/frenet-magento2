<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

/**
 * Class ServiceFinderInterface
 * @package Frenet\Shipping\Model
 */
interface ServiceFinderInterface
{
    /**
     * @param $trackingNumber
     * @return \Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function findByTrackingNumber($trackingNumber);
}
