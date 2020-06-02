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

use Frenet\Shipping\Model\Catalog\ProductType;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class ItemQuantityCalculator
 */
class ItemQuantityCalculator implements ItemQuantityCalculatorInterface
{
    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    public function calculate(QuoteItem $item)
    {
        $type = $item->getProductType();

        if ($item->getParentItem()) {
            $type = $item->getParentItem()->getProductType();
        }

        switch ($type) {
            case ProductType::TYPE_BUNDLE:
                $qty = $this->calculateBundleProduct($item);
                break;

            case ProductType::TYPE_GROUPED:
                $qty = $this->calculateGroupedProduct($item);
                break;

            case ProductType::TYPE_CONFIGURABLE:
                $qty = $this->calculateConfigurableProduct($item);
                break;

            case ProductType::TYPE_VIRTUAL:
            case ProductType::TYPE_DOWNLOADABLE:
            case ProductType::TYPE_SIMPLE:
            default:
                $qty = $this->calculateSimpleProduct($item);
        }

        return (float) max(1, $qty);
    }

    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    private function calculateSimpleProduct(QuoteItem $item)
    {
        return (float) $item->getQty();
    }

    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    private function calculateBundleProduct(QuoteItem $item)
    {
        $bundleQty = (float) $item->getParentItem()->getQty();
        return (float) $item->getQty() * $bundleQty;
    }

    /**
     * @param QuoteItem $item
     *
     * @return float
     */
    private function calculateGroupedProduct(QuoteItem $item)
    {
        return (float) $item->getQty();
    }

    /**
     * The right quantity for configurable products are on the parent item.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    private function calculateConfigurableProduct(QuoteItem $item)
    {
        return (float) $item->getParentItem()->getQty();
    }
}
