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
     * @return string
     */
    public function getWeightAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_WEIGHT;
    }
    
    /**
     * @return string
     */
    public function getHeightAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_HEIGHT;
    }
    
    /**
     * @return string
     */
    public function getLengthAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_LENGTH;
    }
    
    /**
     * @return string
     */
    public function getWidthAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_WIDTH;
    }
}
