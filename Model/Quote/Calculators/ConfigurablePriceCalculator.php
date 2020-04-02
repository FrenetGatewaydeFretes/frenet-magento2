<?php
/**
 * Copyright © MagedIn. All rights reserved.
 * See COPYING.txt for license details.
 *
 * @author Tiago Sampaio <tiago.sampaio@magedin.com>
 */
declare(strict_types = 1);

namespace Frenet\Shipping\Model\Quote\Calculators;

use Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class DefaultPriceCalculator
 *  */
class ConfigurablePriceCalculator implements PriceCalculatorInterface
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
        $parentItem = $item->getParentItem();
        return $parentItem->getPrice();
    }

    /**
     * @inheritDoc
     */
    public function getFinalPrice(QuoteItem $item) : float
    {
        $parentItem = $item->getParentItem();
        return $parentItem->getRowTotal() / $this->itemQuantityCalculator->calculate($item);
    }
}
