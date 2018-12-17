<?php
declare(strict_types = 1);

namespace Frenet\Shipping\Model\Catalog\Product;

use Frenet\Shipping\Api\Data\AttributesMappingInterface;

/**
 * Class AttributesMapping
 *
 * @package Frenet\Shipping\Model\Catalog\Product
 */
class AttributesMapping implements AttributesMappingInterface
{
    /**
     * {@inheritdoc}
     */
    public function getWeightAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_WEIGHT;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getHeightAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_HEIGHT;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLengthAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_LENGTH;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getWidthAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_WIDTH;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLeadTimeAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_LEAD_TIME;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFragileAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_FRAGILE;
    }
}
