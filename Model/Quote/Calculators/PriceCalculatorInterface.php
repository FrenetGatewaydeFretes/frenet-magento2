<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Quote\Calculators;

use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class PriceCalculatorInterface
 *
 * @package Frenet\Shipping\Model\Quote\Calculators
 */
interface PriceCalculatorInterface
{
    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getPrice(QuoteItem $item) : float;

    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getFinalPrice(QuoteItem $item) : float;
}
