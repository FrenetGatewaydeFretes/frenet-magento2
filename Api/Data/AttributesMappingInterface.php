<?php
declare(strict_types = 1);

namespace Frenet\Shipping\Api\Data;

/**
 * Class AttributesMappingInterface
 *
 * @package Frenet\Shipping\Api
 */
interface AttributesMappingInterface
{
    /**
     * @var string
     */
    const DEFAULT_ATTRIBUTE_WEIGHT = 'weight';
    /**
     * @var string
     */
    const DEFAULT_ATTRIBUTE_HEIGHT = 'volume_height';
    /**
     * @var string
     */
    const DEFAULT_ATTRIBUTE_WIDTH = 'volume_width';
    /**
     * @var string
     */
    const DEFAULT_ATTRIBUTE_LENGTH = 'volume_length';
    
    /**
     * @return string
     */
    public function getWeightAttributeCode();
    
    /**
     * @return string
     */
    public function getHeightAttributeCode();
    
    /**
     * @return string
     */
    public function getLengthAttributeCode();
    
    /**
     * @return string
     */
    public function getWidthAttributeCode();
}
