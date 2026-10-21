<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Setup\Patch\Data;

use Frenet\Shipping\Model\Catalog\Product\AttributesMappingInterface;
use Magento\Catalog\Model\Product\Attribute\Backend\Boolean as BooleanBackend;
use Magento\Catalog\Model\Product\Attribute\Source\Boolean as BooleanSource;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Defines the product shipping attributes (package dimensions, lead time, fragile flag) the install patch creates.
 */
class AttributeContainer implements DataPatchInterface
{
    /**
     * @var mixed[][]
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
            'backend'     => BooleanBackend::class,
            'source'      => BooleanSource::class,
        ],
    ];

    /**
     * @var string[]
     */
    private $translatable = [
        'label', 'description', 'note'
    ];

    /**
     * @inheritDoc
     */
    public function apply()
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Returns the module version this patch was introduced in.
     *
     * @return string
     */
    public static function getVersion()
    {
        return '2.4.5';
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [ "frenetshipping-2.4.5.4" ];
    }

    /**
     * Returns the translated definition of one attribute, all of them when no code is given, or false if unknown.
     *
     * @param string|null $attributeCode
     *
     * @return mixed[]|bool
     */
    public function getAttributeProperties($attributeCode = null)
    {
        if (empty($attributeCode)) {
            $result = [];

            /** @var mixed[] $attribute */
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
     * Wraps the label, description and note of the attribute definition in translatable phrases.
     *
     * @param mixed[] $attribute
     *
     * @return mixed[]
     */
    private function applyTranslations(array $attribute) : array
    {
        foreach ($this->translatable as $translatable) {
            if (!isset($attribute[$translatable])) {
                continue;
            }

            $attribute[$translatable] = __($attribute[$translatable]);
        }

        return $attribute;
    }

    /**
     * Checks whether a non-empty definition exists for the attribute code.
     *
     * @param string $attributeCode
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
