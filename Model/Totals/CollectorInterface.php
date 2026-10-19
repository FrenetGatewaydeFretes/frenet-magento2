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

namespace Frenet\Shipping\Model\Totals;

use Magento\Quote\Model\Quote;

/**
 * Extension point that contributes one amount to the additions or discounts of the declared invoice value.
 */
interface CollectorInterface
{
    /**
     * Returns the amount this collector adds to or discounts from the quote's declared invoice value.
     *
     * @param Quote $quote
     *
     * @return float
     */
    public function collect(Quote $quote);
}
