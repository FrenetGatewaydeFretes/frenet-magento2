<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Config\Source\Catalog\Product\Quote;

use Frenet\Shipping\Model\Catalog\ProductType;

/**
 * Class ProductTypes
 *
 * @package Frenet\Shipping\Model\Config\Source\Catalog\Product\Quote
 */
class ProductTypes implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var array
     */
    private $options = [];

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $options = [];

        foreach ($this->toArray() as $code => $label) {
            $options[] = [
                'label' => $label,
                'value' => $code,
            ];
        }

        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $this->options = [
            ProductType::TYPE_SIMPLE       => __('Simple Products'),
            ProductType::TYPE_CONFIGURABLE => __('Configurable Products'),
            ProductType::TYPE_BUNDLE       => __('Bundle Products'),
            ProductType::TYPE_GROUPED      => __('Grouped Products'),
        ];

        return $this->options;
    }
}
