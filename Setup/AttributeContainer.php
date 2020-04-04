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

namespace Frenet\Shipping\Setup;

use Frenet\Shipping\Api\Data\AttributesMappingInterface;

/**
 * Class AttributeContainer
 *
 * @package Frenet\Shipping\Setup
 */
class AttributeContainer
{
    /**
     * @var array
     */
    private $attributes = [
        AttributesMappingInterface::DEFAULT_ATTRIBUTE_LENGTH    => [
            'label'       => 'Length (cm)',
            'description' => "Product's package length (for shipping calculation, minimum of 16cm).",
            'note'        => "Product's package length (for shipping calculation, minimum of 16cm).",
            'default'     => 16,
            'type'        => 'int',
        ],
        AttributesMappingInterface::DEFAULT_ATTRIBUTE_HEIGHT    => [
            'label'       => 'Height (cm)',
            'description' => "Product's package height (for shipping calculation, minimum of 2cm).",
            'note'        => "Product's package height (for shipping calculation, minimum of 2cm).",
            'default'     => 2,
            'type'        => 'int',
        ],
        AttributesMappingInterface::DEFAULT_ATTRIBUTE_WIDTH     => [
            'label'       => 'Width (cm)',
            'description' => "Product's package width (for shipping calculation, minimum of 11cm).",
            'note'        => "Product's package width (for shipping calculation, minimum of 11cm).",
            'default'     => 11,
            'type'        => 'int',
        ],
        AttributesMappingInterface::DEFAULT_ATTRIBUTE_LEAD_TIME => [
            'label'       => 'Lead Time (days)',
            'description' => "Product's manufacturing time (for shipping calculation).",
            'note'        => "Product's manufacturing time (for shipping calculation).",
            'default'     => 0,
            'type'        => 'int',
        ],
        AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE   => [
            'label'       => 'Is Product Fragile?',
            'description' => 'Whether the product contains any fragile materials (for shipping calculation).',
            'note'        => 'Whether the product contains any fragile materials (for shipping calculation).',
            'default'     => false,
            'type'        => 'int',
            'input'       => 'boolean',
            'backend'     => \Magento\Catalog\Model\Product\Attribute\Backend\Boolean::class,
            'source'      => \Magento\Catalog\Model\Product\Attribute\Source\Boolean::class,
        ],
    ];

    /**
     * @var array
     */
    private $translatable = [
        'label', 'description', 'note'
    ];

    /**
     * @param string $attributeCode
     *
     * @return array|bool
     */
    public function getAttributeProperties($attributeCode = null)
    {
        if (empty($attributeCode)) {
            $result = [];

            /** @var array $attribute */
            foreach ($this->attributes as $code => $attribute) {
                $result[$code] = $this->applyTranslations($attribute);
            }

            return $result;
        }

        if (!$this->attributeExists($attributeCode)) {
            return false;
        }

        return $this->applyTranslations($this->attributes[$attributeCode]);
    }

    /**
     * @param array $attribute
     *
     * @return array
     */
    private function applyTranslations(array $attribute) : array
    {
        foreach ($this->translatable as $translatable) {
            if (!isseT($attribute[$translatable])) {
                continue;
            }

            $attribute[$translatable] = __($attribute[$translatable]);
        }

        return $attribute;
    }

    /**
     * @param $attributeCode
     *
     * @return bool
     */
    private function attributeExists($attributeCode) : bool
    {
        if (!isset($this->attributes[$attributeCode])) {
            return false;
        }

        if (empty($this->attributes[$attributeCode])) {
            return false;
        }

        return true;
    }
}
