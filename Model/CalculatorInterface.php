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

use Frenet\Shipping\Model\Packages\PackageItem;

/**
 * Class CalculatorInterface
 *
 * @package Frenet\Shipping\Model
 */
interface CalculatorInterface
{
    /**
     * @return PackageItem[]
     */
    public function getQuote() : array;
}
