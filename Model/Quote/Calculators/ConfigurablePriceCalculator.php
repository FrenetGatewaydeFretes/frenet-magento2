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
class ConfigurablePriceCalculator implements PriceCalculatorInterface
{
    /**
     * @var ItemQuantityCalculatorInterface
     */
    private $itemQtyCalculator;

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
        $parentItem = $item->getParentItem();
        return $parentItem->getPrice();
    }

    /**
     * @inheritDoc
     */
    public function getFinalPrice(QuoteItem $item): float
    {
        $parentItem = $item->getParentItem();

        if (!$parentItem->getRowTotal()) {
            $parentItem->calcRowTotal();
        }

        return $parentItem->getRowTotal() / $this->itemQtyCalculator->calculate($item);
    }
}
