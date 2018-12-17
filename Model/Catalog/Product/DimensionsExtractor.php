<?php
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
     * @var \Magento\Catalog\Model\ResourceModel\ProductFactory
     */
    private $productResourceFactory;
    
    /**
     * @var \Frenet\Shipping\Api\Data\AttributesMappingInterface
     */
    private $attributesMapping;
    
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\ProductFactory $productResourceFactory,
        \Frenet\Shipping\Api\Data\AttributesMappingInterface $attributesMapping
    ) {
        $this->productResourceFactory = $productResourceFactory;
        $this->attributesMapping = $attributesMapping;
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
     * {@inheritdoc}
     */
    public function getWeight()
    {
        return (float) $this->extractData($this->attributesMapping->getWeightAttributeCode());
    }
    
    /**
     * {@inheritdoc}
     */
    public function getHeight()
    {
        return (float) $this->extractData($this->attributesMapping->getHeightAttributeCode());
    }
    
    /**
     * {@inheritdoc}
     */
    public function getWidth()
    {
        return (float) $this->extractData($this->attributesMapping->getWidthAttributeCode());
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLength()
    {
        return (float) $this->extractData($this->attributesMapping->getLengthAttributeCode());
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
        
        if ($this->product->getData($key)) {
            return $this->product->getData($key);
        }
        
        /** @var \Magento\Catalog\Model\ResourceModel\Product $resource */
        $resource = $this->productResourceFactory->create();
    
        $resource->getAttributeRawValue($this->product->getId(), $key, $this->product->getStore());
        
        return null;
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
