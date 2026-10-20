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

use Frenet\ObjectType\Entity\Shipping\Quote\Service;

/**
 * Entry point the carrier uses to quote the current rate request against the Frenet API.
 */
interface CalculatorInterface
{
    /**
     * Quotes the current rate request and returns the Frenet services that become shipping rates.
     *
     * @return Service[]
     */
    public function getQuote() : array;
}
