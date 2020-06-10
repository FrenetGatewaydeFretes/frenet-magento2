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
 * Class ItemPriceCalculator
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class ItemPriceCalculator
{
    /**
     * @var ItemQuantityCalculatorInterface
     */
    private $itemQtyCalculator;

    /**
     * @var PriceCalculatorFactory
     */
    private $priceCalculatorFactory;

    public function __construct(
        ItemQuantityCalculatorInterface $itemQtyCalculator,
        PriceCalculatorFactory $priceCalculatorFactory
    ) {
        $this->itemQtyCalculator = $itemQtyCalculator;
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
