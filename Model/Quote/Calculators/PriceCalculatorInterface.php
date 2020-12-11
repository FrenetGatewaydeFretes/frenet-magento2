<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Quote\Calculators;

use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Class PriceCalculatorInterface
 */
interface PriceCalculatorInterface
{
    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getPrice(QuoteItem $item): float;

    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getFinalPrice(QuoteItem $item): float;
}
