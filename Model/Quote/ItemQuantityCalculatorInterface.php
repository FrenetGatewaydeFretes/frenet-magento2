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

namespace Frenet\Shipping\Model\Quote;

/**
 * Class ItemQuantityCalculatorInterface
 *
 * @package Frenet\Shipping\Model\Quote
 */
interface ItemQuantityCalculatorInterface
{
    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return integer
     */
    public function calculate(\Magento\Quote\Model\Quote\Item $item);
}
