<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Quote;

use Magento\Quote\Model\Quote\Item;

/**
 * Class ItemQuantityCalculator
 *
 * @package Frenet\Shipping\Model\Quote
 */
class ItemQuantityCalculator implements ItemQuantityCalculatorInterface
{
    /**
     * @param Item $item
     *
     * @return integer
     */
    public function calculate(Item $item)
    {
        $type = $item->getProductType();
        
        if ($item->getParentItemId()) {
            $type = $item->getParentItem()->getProductType();
        }
        
        switch ($type) {
            case \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE:
                $qty = $this->calculateBundleProduct($item);
                break;
                
            case \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE:
                $qty = $this->calculateGroupedProduct($item);
                break;
                
            case \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE:
                $qty = $this->calculateConfigurableProduct($item);
                break;
                
            case \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL:
            case \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE:
            case \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE:
            default:
                $qty = $this->calculateSimpleProduct($item);
        }
        
        return (int) max(1, $qty);
    }
    
    /**
     * @param Item $item
     *
     * @return float|int|mixed
     */
    private function calculateSimpleProduct(Item $item)
    {
        return $item->getQty();
    }
    
    /**
     * @param Item $item
     *
     * @return float|int|mixed
     */
    private function calculateBundleProduct(Item $item)
    {
        $bundleQty = (float) $item->getParentItem()->getQty();
        return $item->getQty() * $bundleQty;
    }
    
    /**
     * @param Item $item
     *
     * @return float|int|mixed
     */
    private function calculateGroupedProduct(Item $item)
    {
        return $item->getQty();
    }
    
    /**
     * The right quantity for configurable products are on the parent item.
     *
     * @param Item $item
     *
     * @return float|int|mixed
     */
    private function calculateConfigurableProduct(Item $item)
    {
        $qty = $item->getParentItemId() ? $item->getParentItem()->getQty() : $item->getQty();
        return $qty;
    }
}
