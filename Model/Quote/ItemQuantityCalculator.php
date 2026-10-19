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
use Frenet\Shipping\Model\ConfigInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Resolves the shippable quantity of a cart item per product type, capped at the configured maximum unit quantity.
 */
class ItemQuantityCalculator implements ItemQuantityCalculatorInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
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

        return (float) min($this->config->getMaxUnitQuantity(), max(1, $qty));
    }

    /**
     * Returns the quantity for a simple product.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    private function calculateSimpleProduct(QuoteItem $item)
    {
        return (float) $item->getQty();
    }

    /**
     * Returns the quantity for a bundle product, scaled by the parent item's quantity.
     *
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
     * Returns the quantity for a grouped product.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    private function calculateGroupedProduct(QuoteItem $item)
    {
        return (float) $item->getQty();
    }

    /**
     * Returns the quantity for a configurable product, which lives on the parent item.
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
