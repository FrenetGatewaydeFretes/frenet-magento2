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
 * Class ServiceFinderInterface
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
