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
 * Prices a cart item for the declared shipment value, with one implementation per product type.
 */
interface PriceCalculatorInterface
{
    /**
     * Returns the unit price of the item.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getPrice(QuoteItem $item): float;

    /**
     * Returns the final unit price of the item, derived from its row total and shippable quantity.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getFinalPrice(QuoteItem $item): float;
}
