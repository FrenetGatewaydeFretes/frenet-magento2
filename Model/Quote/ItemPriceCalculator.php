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

use Frenet\Shipping\Model\Quote\Calculators\PriceCalculatorFactory;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Delegates item pricing to the calculator that matches the item's product type.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class ItemPriceCalculator
{
    /**
     * @var PriceCalculatorFactory
     */
    private $priceCalculatorFactory;

    /**
     * @param PriceCalculatorFactory $priceCalculatorFactory
     */
    public function __construct(
        PriceCalculatorFactory $priceCalculatorFactory
    ) {
        $this->priceCalculatorFactory = $priceCalculatorFactory;
    }

    /**
     * Returns the item's unit price from the calculator for its product type.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getPrice(QuoteItem $item)
    {
        return $this->priceCalculatorFactory->create($item)->getPrice($item);
    }

    /**
     * Returns the item's final unit price from the calculator for its product type.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getFinalPrice(QuoteItem $item)
    {
        return $this->priceCalculatorFactory->create($item)->getFinalPrice($item);
    }
}
