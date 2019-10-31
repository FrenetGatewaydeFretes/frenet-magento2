<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Packages;

use Frenet\Shipping\Api\Data\AttributesMappingInterface;

/**
 * Class PackageItem
 *
 * @package Frenet\Shipping\Model\Packages
 */
class PackageItem
{
    /**
     * @var \Magento\Quote\Api\Data\CartItemInterface
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
     * @var \Magento\Quote\Api\Data\CartItemInterface
     */
    private $storeManagement;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\ProductFactory
     */
    private $productResourceFactory;

    /**
     * @var \Frenet\Shipping\Api\WeightConverterInterface
     */
    private $weightConverter;

    /**
     * @var \Frenet\Shipping\Model\Catalog\Product\CategoryExtractor
     */
    private $categoryExtractor;

    /**
     * @var \Frenet\Shipping\Api\Data\DimensionsExtractorInterface
     */
    private $dimensionsExtractor;

    /**
     * @var Frenet\Shipping\Model\Quote\ItemPriceCalculator
     */
    private $itemPriceCalculator;

    /**
     * PackageItem constructor.
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface                $cartItem
     * @param \Magento\Store\Model\StoreManagerInterface               $storeManagement
     * @param \Magento\Catalog\Model\ResourceModel\ProductFactory      $productResourceFactory
     * @param \Frenet\Shipping\Api\WeightConverterInterface            $weightConverter
     * @param \Frenet\Shipping\Model\Catalog\Product\CategoryExtractor $categoryExtractor
     * @param \Frenet\Shipping\Api\Data\DimensionsExtractorInterface   $dimensionsExtractor
     * @param \Frenet\Shipping\Model\Quote\ItemPriceCalculator          $itemPriceCalculator
     */
    public function __construct(
        \Magento\Quote\Api\Data\CartItemInterface $cartItem,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Catalog\Model\ResourceModel\ProductFactory $productResourceFactory,
        \Frenet\Shipping\Api\WeightConverterInterface $weightConverter,
        \Frenet\Shipping\Model\Catalog\Product\CategoryExtractor $categoryExtractor,
        \Frenet\Shipping\Api\Data\DimensionsExtractorInterface $dimensionsExtractor,
        \Frenet\Shipping\Model\Quote\ItemPriceCalculator $itemPriceCalculator
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
     * @return \Magento\Quote\Api\Data\CartItemInterface
     */
    public function getCartItem()
    {
        return $this->cartItem;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     *
     * @return $this
     */
    public function setCartItem(\Magento\Quote\Api\Data\CartItemInterface $item)
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
        /** @todo There will be needed a extractor here. */
        return (float) $this->itemPriceCalculator->getPrice($this->cartItem);
    }

    /**
     * @return float
     */
    public function getFinalPrice()
    {
        /** @todo There will be needed a extractor here. */
        return (float) $this->itemPriceCalculator->getFinalPrice($this->cartItem);
    }

    /**
     * @return float
     */
    public function getTotalPrice()
    {
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
        return $this->weightConverter->convertToKg($this->dimensionsExtractor->getWeight());
    }

    /**
     * @return float
     */
    public function getTotalWeight()
    {
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
        if ($this->isInitialized) {
            return $this;
        }

        $this->dimensionsExtractor->setProduct($this->cartItem->getProduct());

        $this->isInitialized = true;
        return $this;
    }
}
