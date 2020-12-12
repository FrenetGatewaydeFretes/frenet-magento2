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

use Frenet\Shipping\Model\Catalog\Product\AttributesMappingInterface;
use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\Catalog\Product\CategoryExtractor;
use Frenet\Shipping\Model\Quote\ItemPriceCalculator;
use Frenet\Shipping\Model\WeightConverterInterface;
use Magento\Catalog\Model\ResourceModel\ProductFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Class PackageItem
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
     * @var StoreManagerInterface
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
     * @param QuoteItem                    $cartItem
     * @param StoreManagerInterface        $storeManagement
     * @param ProductFactory               $productResourceFactory
     * @param WeightConverterInterface     $weightConverter
     * @param CategoryExtractor            $categoryExtractor
     * @param DimensionsExtractorInterface $dimensionsExtractor
     * @param ItemPriceCalculator          $itemPriceCalculator
     */
    public function __construct(
        QuoteItem $cartItem,
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
     * @return QuoteItem
     */
    public function getCartItem()
    {
        return $this->cartItem;
    }

    /**
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
    public function setQty($qty)
    {
        $this->qty = (float) $qty;
        return $this;
    }

    /**
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
                $this->storeManagement->getStore()
            );

            return (bool) $value;
        } catch (\Exception $e) {
            return false;
        }
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
