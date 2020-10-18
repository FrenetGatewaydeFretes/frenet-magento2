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

namespace Frenet\Shipping\Model\Quote\Calculators;

use Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Class DefaultPriceCalculator
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class DefaultPriceCalculator implements PriceCalculatorInterface
{
    /**
     * @var ItemQuantityCalculatorInterface
     */
    private $itemQuantityCalculator;

    public function __construct(
        ItemQuantityCalculatorInterface $itemQuantityCalculator
    ) {
        $this->itemQuantityCalculator = $itemQuantityCalculator;
    }

    /**
     * @inheritDoc
     */
    public function getPrice(QuoteItem $item) : float
    {
        return $item->getPrice();
    }

    /**
     * @inheritDoc
     */
    public function getFinalPrice(QuoteItem $item) : float
    {
        if (!$item->getRowTotal()) {
            $item->calcRowTotal();
        }

        /**
         * If the item price is still not calculated then fallback to product final price.
         */
        if (!$item->getRowTotal()) {
            $basePrice = $item->getProduct()->getFinalPrice($item->getQty());
            $item->setRowTotal($basePrice * $item->getQty());
        }

        return $item->getRowTotal() / $this->itemQuantityCalculator->calculate($item);
    }
}
