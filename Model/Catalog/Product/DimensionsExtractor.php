<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Catalog\Product;

use Frenet\Shipping\Api\Data\ProductExtractorInterface;

/**
 * Class DataExtractor
 *
 * @package Frenet\Shipping\Model\Catalog\Product
 */
class DimensionsExtractor implements ProductExtractorInterface
{
    /**
     * @var \Magento\Catalog\Model\Product
     */
    private $product;

    /**
     * @var \Magento\Quote\Api\Data\CartItemInterface
     */
    private $cartItem;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\ProductFactory
     */
    private $productResourceFactory;

    /**
     * @var \Frenet\Shipping\Api\Data\AttributesMappingInterface
     */
    private $attributesMapping;

    /**
     * @var \Frenet\Shipping\Model\Config
     */
    private $config;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\ProductFactory $productResourceFactory,
        \Frenet\Shipping\Api\Data\AttributesMappingInterface $attributesMapping,
        \Frenet\Shipping\Model\Config $config
    ) {
        $this->productResourceFactory = $productResourceFactory;
        $this->attributesMapping = $attributesMapping;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function setProduct(\Magento\Catalog\Api\Data\ProductInterface $product)
    {
        if ($this->validateProduct($product)) {
            $this->product = $product;
        }

        return $this;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartItemInterface $cartItem
     *
     * @return $this
     */
    public function setProductByCartItem(\Magento\Quote\Api\Data\CartItemInterface $cartItem)
    {
        $this->cartItem = $cartItem;
        $this->setProduct($this->cartItem->getProduct());
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getWeight()
    {
        $value = $this->extractData($this->attributesMapping->getWeightAttributeCode());

        if (empty($value)) {
            $value = $this->config->getDefaultWeight();
        }

        return (float) $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeight()
    {
        $value = $this->extractData($this->attributesMapping->getHeightAttributeCode());

        if (empty($value)) {
            $value = $this->config->getDefaultHeight();
        }

        return (float) $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getWidth()
    {
        $value = $this->extractData($this->attributesMapping->getWidthAttributeCode());

        if (empty($value)) {
            $value = $this->config->getDefaultWidth();
        }

        return (float) $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getLength()
    {
        $value = $this->extractData($this->attributesMapping->getLengthAttributeCode());

        if (empty($value)) {
            $value = $this->config->getDefaultLength();
        }

        return (float) $value;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    private function extractData($key)
    {
        if (!$this->product) {
            return null;
        }

        if ($this->cartItem->getData($key)) {
            return $this->cartItem->getData($key);
        }

        if ($this->product->getData($key)) {
            return $this->product->getData($key);
        }

        $value = $this->productResourceFactory->create()->getAttributeRawValue(
            $this->product->getId(),
            $key,
            $this->product->getStore()
        );

        return $value;
    }

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     *
     * @return bool
     */
    private function validateProduct(\Magento\Framework\DataObject $product)
    {
        if (!$product->getId()) {
            return false;
        }

        if (!$product->getStoreId()) {
            return false;
        }

        return true;
    }
}
