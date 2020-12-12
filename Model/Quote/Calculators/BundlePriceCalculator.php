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
use Magento\Bundle\Model\Product\Price;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Class DefaultPriceCalculator
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class BundlePriceCalculator implements PriceCalculatorInterface
{
    /**
     * @var ItemQuantityCalculatorInterface
     */
    private $itemQtyCalculator;

    /**
     * BundlePriceCalculator constructor.
     *
     * @param ItemQuantityCalculatorInterface $itemQuantityCalculator
     */
    public function __construct(
        ItemQuantityCalculatorInterface $itemQuantityCalculator
    ) {
        $this->itemQtyCalculator = $itemQuantityCalculator;
    }

    /**
     * @inheritDoc
     */
    public function getPrice(QuoteItem $item): float
    {
        if ($this->isPriceTypeFixed($item)) {
            return $this->calculatePartialValue($item);
        }

        return $item->getPrice();
    }

    /**
     * @inheritDoc
     */
    public function getFinalPrice(QuoteItem $item): float
    {
        if ($this->isPriceTypeFixed($item)) {
            return $this->calculatePartialValue($item);
        }

        return $item->getRowTotal() / $this->itemQtyCalculator->calculate($item);
    }

    /**
     * This is an alternative solution for when the bundle has the Price Type Fixed.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    private function calculatePartialValue(QuoteItem $item)
    {
        /** @var QuoteItem $bundle */
        $bundle = $item->getParentItem();

        if (!$bundle->getRowTotal()) {
            $bundle->calcRowTotal();
        }

        $rowTotal = (float) $bundle->getRowTotal() / $this->itemQtyCalculator->calculate($item);

        return (float) ($rowTotal / count($bundle->getChildren()));
    }

    /**
     * @param QuoteItem $item
     *
     * @return bool
     */
    private function isPriceTypeFixed(QuoteItem $item): bool
    {
        /** @var QuoteItem $bundle */
        $bundle = $this->getBundleItem($item);

        if (Price::PRICE_TYPE_FIXED == $bundle->getProduct()->getPriceType()) {
            return true;
        }

        return false;
    }

    /**
     * @param QuoteItem $item
     *
     * @return QuoteItem
     */
    private function getBundleItem(QuoteItem $item): QuoteItem
    {
        return $item->getParentItem();
    }
}
