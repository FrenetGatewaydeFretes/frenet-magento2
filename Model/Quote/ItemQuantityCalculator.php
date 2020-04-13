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
use Magento\Quote\Model\Quote\Item;

/**
 * Class ItemQuantityCalculator
 */
class ItemQuantityCalculator implements ItemQuantityCalculatorInterface
{
    /**
     * @param Item $item
     *
     * @return float
     */
    public function calculate(Item $item)
    {
        $type = $item->getProductType();

        if ($item->getParentItemId()) {
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
     * @param Item $item
     *
     * @return float
     */
    private function calculateSimpleProduct(Item $item)
    {
        return (float) $item->getQty();
    }

    /**
     * @param Item $item
     *
     * @return float
     */
    private function calculateBundleProduct(Item $item)
    {
        $bundleQty = (float) $item->getParentItem()->getQty();
        return (float) $item->getQty() * $bundleQty;
    }

    /**
     * @param Item $item
     *
     * @return float
     */
    private function calculateGroupedProduct(Item $item)
    {
        return (float) $item->getQty();
    }

    /**
     * The right quantity for configurable products are on the parent item.
     *
     * @param Item $item
     *
     * @return float
     */
    private function calculateConfigurableProduct(Item $item)
    {
        return (float) $item->getParentItem()->getQty();
    }
}
