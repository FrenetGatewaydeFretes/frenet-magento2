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
use Magento\Framework\DataObject;

/**
 * Class ConfigurableBuilder
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

        $this->buildDefaultOptions($product, $request);
    }

    /**
     * @param ProductInterface $product
     * @param DataObject       $request
     *
     * @return void
     */
    private function buildDefaultOptions(ProductInterface $product, DataObject $request)
    {
        $options = [];

        /** @var \Magento\Catalog\Model\Product\Type\AbstractType $typeInstance */
        $typeInstance = $product->getTypeInstance();
        $configurableOptions = $typeInstance->getConfigurableOptions($product);

        /**
         * Get the default attribute options.
         */
        foreach ($configurableOptions as $configurableOptionId => $configurableOption) {
            /** @var array $option */
            $option = array_shift($configurableOption);
            $options[$configurableOptionId] = $option['value_index'];
        }

        $request->setData('super_attribute', $options);
    }
}
