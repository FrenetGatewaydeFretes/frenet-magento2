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
 * Resolves how many units of a cart item are shipped, taking the parent item of bundles and configurables into account.
 */
interface ItemQuantityCalculatorInterface
{
    /**
     * Calculates the effective quantity of a quote item, capped at the configured maximum.
     *
     * @param AbstractItem $item
     *
     * @return float
     */
    public function calculate(AbstractItem $item);
}
