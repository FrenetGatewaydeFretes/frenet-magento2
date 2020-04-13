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

namespace Frenet\Shipping\Api;

use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class CalculatorInterface
 */
interface CalculatorInterface
{
    /**
     * @param RateRequest $request
     * @return array
     */
    public function getQuote(RateRequest $request);
}
