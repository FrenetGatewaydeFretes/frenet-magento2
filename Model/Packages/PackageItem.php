<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Packages;

use Frenet\Shipping\Model\Catalog\Product\AttributesMappingInterface;
use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\Catalog\Product\CategoryExtractor;
use Frenet\Shipping\Model\Quote\ItemPriceCalculator;
use Frenet\Shipping\Model\WeightConverterInterface;
use Magento\Catalog\Model\ResourceModel\ProductFactory;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class PackageItem
 *
 * @package Frenet\Shipping\Model\Packages
 */
class PackageItem
{
    /**
     * @var CartItemInterface
     */
    private $cartItem;

    /**
     * @var float
     */
    private $qty;

    /**
     * @var bool
     */
    private $isInitialized = false;

    /**
     * @var CartItemInterface
     */
    private $storeManagement;

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
     * PackageItem constructor.
     *
     * @param CartItemInterface            $cartItem
     * @param StoreManagerInterface        $storeManagement
     * @param ProductFactory               $productResourceFactory
     * @param WeightConverterInterface     $weightConverter
     * @param CategoryExtractor            $categoryExtractor
     * @param DimensionsExtractorInterface $dimensionsExtractor
     * @param ItemPriceCalculator          $itemPriceCalculator
     */
    public function __construct(
        CartItemInterface $cartItem,
        StoreManagerInterface $storeManagement,
        ProductFactory $productResourceFactory,
        WeightConverterInterface $weightConverter,
        CategoryExtractor $categoryExtractor,
        DimensionsExtractorInterface $dimensionsExtractor,
        ItemPriceCalculator $itemPriceCalculator
    ) {
        $this->cartItem = $cartItem;
        $this->storeManagement = $storeManagement;
        $this->productResourceFactory = $productResourceFactory;
        $this->weightConverter = $weightConverter;
        $this->categoryExtractor = $categoryExtractor;
        $this->dimensionsExtractor = $dimensionsExtractor;
        $this->itemPriceCalculator = $itemPriceCalculator;
    }

    /**
     * @return CartItemInterface
     */
    public function getCartItem()
    {
        return $this->cartItem;
    }

    /**
     * @param CartItemInterface $item
     *
     * @return $this
     */
    public function setCartItem(CartItemInterface $item)
    {
        $this->cartItem = $item;
        return $this;
    }

    /**
     * @return float
     */
    public function getQty()
    {
        return (float) $this->qty ?: 1;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        $this->initProduct();

        /** @todo There will be needed a extractor here. */
        return (float) $this->itemPriceCalculator->getPrice($this->cartItem);
    }

    /**
     * @return float
     */
    public function getFinalPrice()
    {
        $this->initProduct();

        /** @todo There will be needed a extractor here. */
        return (float) $this->itemPriceCalculator->getFinalPrice($this->cartItem);
    }

    /**
     * @return float
     */
    public function getTotalPrice()
    {
        $this->initProduct();
        return (float) $this->getFinalPrice() * $this->getQty();
    }

    /**
     * @param float $qty
     *
     * @return $this
     */
    public function setQty(float $qty)
    {
        $this->qty = $qty;
        return $this;
    }

    /**
     * @param bool $useParentItemIfAvailable
     *
     * @return bool|\Magento\Catalog\Model\Product
     */
    public function getProduct($useParentItemIfAvailable = false)
    {
        $this->initProduct();

        if ((true === $useParentItemIfAvailable) && $this->cartItem->getParentItem()) {
            return $this->getProduct($this->cartItem->getParentItem());
        }

        /** @var \Magento\Catalog\Model\Product $product */
        return $this->cartItem->getProduct();
    }

    /**
     * @return string|null
     */
    public function getSku()
    {
        return $this->cartItem->getSku();
    }

    /**
     * @return float
     */
    public function getWeight()
    {
        $this->initProduct();
        return $this->weightConverter->convertToKg($this->dimensionsExtractor->getWeight());
    }

    /**
     * @return float
     */
    public function getTotalWeight()
    {
        $this->initProduct();
        return (float) ($this->getWeight() * $this->getQty());
    }

    /**
     * @return float
     */
    public function getLength()
    {
        $this->initProduct();
        return $this->dimensionsExtractor->getLength();
    }

    /**
     * @return float
     */
    public function getHeight()
    {
        $this->initProduct();
        return $this->dimensionsExtractor->getHeight();
    }

    /**
     * @return float
     */
    public function getWidth()
    {
        $this->initProduct();
        return $this->dimensionsExtractor->getWidth();
    }

    /**
     * @return string|null
     */
    public function getProductCategories()
    {
        $this->initProduct();
        return $this->categoryExtractor->getProductCategories($this->getProduct(true));
    }

    /**
     * @return mixed
     */
    public function isProductFragile()
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->getProduct();

        if ($product->hasData(AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE)) {
            return (bool) $product->getData(AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE);
        }

        try {
            /** @var \Magento\Catalog\Model\ResourceModel\Product $resource */
            $resource = $this->productResourceFactory->create();
            $value = (bool) $resource->getAttributeRawValue(
                $product->getId(),
                AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE,
                $this->storeManagement->getStore()
            );

            return (bool) $value;
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * @return $this
     */
    private function initProduct()
    {
        $this->dimensionsExtractor->setProductByCartItem($this->cartItem);
        return $this;
    }
}
