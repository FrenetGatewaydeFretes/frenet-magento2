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
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Lists the product types that can be enabled for the shipping quote on the product page.
 */
class ProductTypes implements OptionSourceInterface
{
    /**
     * @var Phrase[]
     */
    private $options = [];

    /**
     * Returns the supported product types as value-label option rows.
     *
     * @return mixed[][]
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
     * Returns the translated product type labels keyed by product type code.
     *
     * @return Phrase[]
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
