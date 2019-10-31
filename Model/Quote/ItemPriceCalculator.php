<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Quote;

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

    public function __construct(
        ItemQuantityCalculatorInterface $itemQuantityCalculator
    ) {
        $this->itemQuantityCalculator = $itemQuantityCalculator;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return float
     */
    public function getPrice(\Magento\Quote\Model\Quote\Item $item)
    {
        return $this->getRealItem($item)->getPrice();
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return float
     */
    public function getFinalPrice(\Magento\Quote\Model\Quote\Item $item)
    {
        $realItem = $this->getRealItem($item);
        return $realItem->getRowTotal() / $this->itemQuantityCalculator->calculate($realItem);
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return \Magento\Quote\Model\Quote\Item
     */
    private function getRealItem(\Magento\Quote\Model\Quote\Item $item)
    {
        $type = $item->getProductType();

        if ($item->getParentItemId()) {
            $type = $item->getParentItem()->getProductType();
        }

        switch ($type) {
            case \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE:
                // $qty = $this->calculateBundleProduct($item);
                break;

            case \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE:
                // $qty = $this->calculateGroupedProduct($item);
                break;

            case \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE:
                return $item->getParentItem();

            case \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL:
            case \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE:
            case \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE:
            default:
                return $item;
        }
    }
}
