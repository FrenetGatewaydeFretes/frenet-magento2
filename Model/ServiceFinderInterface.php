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

use Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Resolves which Frenet shipping service a tracking number was shipped with.
 */
interface ServiceFinderInterface
{
    /**
     * Returns the Frenet service the tracked shipment was sent with, or null when no service matches.
     *
     * @param string $trackingNumber
     *
     * @return ServiceInterface|null
     * @throws LocalizedException
     */
    public function findByTrackingNumber($trackingNumber);
}
