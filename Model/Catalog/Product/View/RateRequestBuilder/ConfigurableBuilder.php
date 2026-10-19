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

namespace Frenet\Shipping\Model\Catalog\Product\View\RateRequestBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\DataObject;

/**
 * Fills the super attributes of the buy request, defaulting each configurable attribute to its first value.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class ConfigurableBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(ProductInterface $product, DataObject $request, array $options = [])
    {
        if ($options && isset($options['super_attribute'])) {
            $request->setData('super_attribute', $options['super_attribute']);
            return;
        }

        if ($product instanceof Product) {
            $this->buildDefaultOptions($product, $request);
        }
    }

    /**
     * Selects the first value of every configurable attribute of the product.
     *
     * @param Product          $product
     * @param DataObject       $request
     *
     * @return void
     */
    private function buildDefaultOptions(Product $product, DataObject $request)
    {
        $options = [];

        /** @var ConfigurableType $typeInstance */
        $typeInstance = $product->getTypeInstance();
        $configurableOptions = $typeInstance->getConfigurableOptions($product);

        /**
         * Get the default attribute options.
         */
        foreach ($configurableOptions as $configurableOptionId => $configurableOption) {
            /** @var mixed[] $option */
            $option = array_shift($configurableOption);
            $options[$configurableOptionId] = $option['value_index'];
        }

        $request->setData('super_attribute', $options);
    }
}
