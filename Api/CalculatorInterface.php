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

namespace Frenet\Shipping\Api;

use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class CalculatorInterface
 * @package Frenet\Shipping\Api
 */
interface CalculatorInterface
{
    /**
     * @return array
     */
    public function getQuote();
}
