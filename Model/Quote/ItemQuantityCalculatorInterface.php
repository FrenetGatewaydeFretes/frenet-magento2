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

namespace Frenet\Shipping\Model\Quote;

use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Class ItemQuantityCalculatorInterface
 */
interface ItemQuantityCalculatorInterface
{
    /**
     * @param AbstractItem $item
     *
     * @return integer
     */
    public function calculate(AbstractItem $item);
}
