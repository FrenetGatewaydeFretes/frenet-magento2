<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Packages;

use Exception;
use Frenet\Shipping\Model\Catalog\Product\AttributesMappingInterface;
use Frenet\Shipping\Model\Catalog\Product\CategoryExtractor;
use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\Quote\ItemPriceCalculator;
use Frenet\Shipping\Model\WeightConverterInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\ProductFactory;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Wraps a cart item inside a package and exposes the weight, size and price data sent to the Frenet quote API.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class PackageItem
{
    /**
     * @var QuoteItem
     */
    private $cartItem;

    /**
     * @var float
     */
    private $qty;

    /**
     * @var ProductFactory
     */
    private $productResourceFactory;

    /**
     * @var WeightConverterInterface
     */
    private $weightConverter;

    /**
     * @var CategoryExtractor
     */
    private $categoryExtractor;

    /**
     * @var DimensionsExtractorInterface
     */
    private $dimensionsExtractor;

    /**
     * @var ItemPriceCalculator
     */
    private $itemPriceCalculator;

    /**
     * @param QuoteItem                    $cartItem
     * @param ProductFactory               $productResourceFactory
     * @param WeightConverterInterface     $weightConverter
     * @param CategoryExtractor            $categoryExtractor
     * @param DimensionsExtractorInterface $dimensionsExtractor
     * @param ItemPriceCalculator          $itemPriceCalculator
     */
    public function __construct(
        QuoteItem $cartItem,
        ProductFactory $productResourceFactory,
        WeightConverterInterface $weightConverter,
        CategoryExtractor $categoryExtractor,
        DimensionsExtractorInterface $dimensionsExtractor,
        ItemPriceCalculator $itemPriceCalculator
    ) {
        $this->cartItem = $cartItem;
        $this->productResourceFactory = $productResourceFactory;
        $this->weightConverter = $weightConverter;
        $this->categoryExtractor = $categoryExtractor;
        $this->dimensionsExtractor = $dimensionsExtractor;
        $this->itemPriceCalculator = $itemPriceCalculator;
    }

    /**
     * Returns the cart item this package entry was built from.
     *
     * @return QuoteItem
     */
    public function getCartItem()
    {
        return $this->cartItem;
    }

    /**
     * Replaces the cart item this package entry wraps.
     *
     * @param QuoteItem $item
     *
     * @return $this
     */
    public function setCartItem(QuoteItem $item)
    {
        $this->cartItem = $item;
        return $this;
    }

    /**
     * Returns the quantity of the item packed here, defaulting to one when none was set.
     *
     * @return float
     */
    public function getQty()
    {
        return (float) $this->qty ?: 1;
    }

    /**
     * Returns the item's unit price as resolved by the calculator for its product type.
     *
     * @return float
     */
    public function getPrice()
    {
        $this->initProduct();

        /** @todo There will be needed a extractor here. */
        return (float) $this->itemPriceCalculator->getPrice($this->cartItem);
    }

    /**
     * Returns the item's final unit price as resolved by the calculator for its product type.
     *
     * @return float
     */
    public function getFinalPrice()
    {
        $this->initProduct();

        /** @todo There will be needed a extractor here. */
        return (float) $this->itemPriceCalculator->getFinalPrice($this->cartItem);
    }

    /**
     * Returns the final unit price multiplied by the packed quantity.
     *
     * @return float
     */
    public function getTotalPrice()
    {
        $this->initProduct();
        return (float) $this->getFinalPrice() * $this->getQty();
    }

    /**
     * Sets the quantity of the item packed in this package.
     *
     * @param float $qty
     *
     * @return $this
     */
    public function setQty($qty)
    {
        $this->qty = (float) $qty;
        return $this;
    }

    /**
     * Returns the cart item's product, or the parent item's product when requested and available.
     *
     * @param bool $useParentItemIfAvailable
     *
     * @return bool|Product
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function getProduct($useParentItemIfAvailable = false)
    {
        $this->initProduct();

        if ((true === $useParentItemIfAvailable) && $this->cartItem->getParentItem()) {
            return $this->getProduct($this->cartItem->getParentItem());
        }

        /** @var Product $product */
        return $this->cartItem->getProduct();
    }

    /**
     * Returns the cart item's SKU, sent as the shipping item identifier.
     *
     * @return string|null
     */
    public function getSku()
    {
        return $this->cartItem->getSku();
    }

    /**
     * Returns the unit weight converted from the catalog weight unit to kilograms.
     *
     * @return float
     */
    public function getWeight()
    {
        $this->initProduct();
        return $this->weightConverter->convertToKg($this->dimensionsExtractor->getWeight());
    }

    /**
     * Returns the unit weight in kilograms multiplied by the packed quantity.
     *
     * @return float
     */
    public function getTotalWeight()
    {
        $this->initProduct();
        return (float) ($this->getWeight() * $this->getQty());
    }

    /**
     * Returns the product length resolved by the dimensions extractor.
     *
     * @return float
     */
    public function getLength()
    {
        $this->initProduct();
        return $this->dimensionsExtractor->getLength();
    }

    /**
     * Returns the product height resolved by the dimensions extractor.
     *
     * @return float
     */
    public function getHeight()
    {
        $this->initProduct();
        return $this->dimensionsExtractor->getHeight();
    }

    /**
     * Returns the product width resolved by the dimensions extractor.
     *
     * @return float
     */
    public function getWidth()
    {
        $this->initProduct();
        return $this->dimensionsExtractor->getWidth();
    }

    /**
     * Returns the product categories sent to the Frenet API, preferring the parent product when there is one.
     *
     * @return string|null
     */
    public function getProductCategories()
    {
        $this->initProduct();
        return $this->categoryExtractor->getProductCategories($this->getProduct(true));
    }

    /**
     * Reads the fragile flag from the loaded product, falling back to the raw attribute value.
     *
     * @return bool
     */
    public function isProductFragile()
    {
        /** @var Product $product */
        $product = $this->getProduct();

        if ($product->hasData(AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE)) {
            return (bool) $product->getData(AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE);
        }

        try {
            /** @var ProductResource $resource */
            $resource = $this->productResourceFactory->create();
            $value = (bool) $resource->getAttributeRawValue(
                $product->getId(),
                AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE,
                $product->getStore()
            );

            return (bool) $value;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Points the dimensions extractor at this entry's cart item before any of its data is read.
     *
     * @return $this
     */
    private function initProduct()
    {
        $this->dimensionsExtractor->setProductByCartItem($this->cartItem);
        return $this;
    }
}
