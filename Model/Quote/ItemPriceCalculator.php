<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Quote;

use Frenet\Shipping\Model\Quote\Calculators\PriceCalculatorFactory;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class ItemPriceCalculator
 *
 * @package Frenet\Shipping\Model\Quote
 */
class ItemPriceCalculator
{
    /**
     * @var ItemQuantityCalculatorInterface
     */
    private $itemQuantityCalculator;

    /**
     * @var PriceCalculatorFactory
     */
    private $priceCalculatorFactory;

    public function __construct(
        ItemQuantityCalculatorInterface $itemQuantityCalculator,
        PriceCalculatorFactory $priceCalculatorFactory
    ) {
        $this->itemQuantityCalculator = $itemQuantityCalculator;
        $this->priceCalculatorFactory = $priceCalculatorFactory;
    }

    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getPrice(QuoteItem $item)
    {
        return $this->priceCalculatorFactory->create($item)->getPrice($item);
    }

    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    public function getFinalPrice(QuoteItem $item)
    {
        return $this->priceCalculatorFactory->create($item)->getFinalPrice($item);
    }
}
