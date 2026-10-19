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

use Magento\Bundle\Model\Option;
use Magento\Bundle\Model\ResourceModel\Option\Collection as OptionCollection;
use Magento\Bundle\Model\ResourceModel\Selection\Collection as SelectionCollection;
use Magento\Bundle\Model\Selection;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\DataObject;

/**
 * Fills the bundle options of the buy request, defaulting each required option to its default or first selection.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class BundleBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(ProductInterface $product, DataObject $request, array $options = [])
    {
        if ($options && isset($options['bundle_option'])) {
            $option = $options['bundle_option'];
            $qty    = $options['bundle_option_qty'] ?? 1;

            $request->setData('bundle_option', $option);
            $request->setData('bundle_option_qty', $qty);
            return;
        }

        $this->buildDefaultOptions($product, $request);
    }

    /**
     * Selects the default, or else the first, selection of every required bundle option.
     *
     * @param ProductInterface $product
     * @param DataObject       $request
     *
     * @return void
     */
    private function buildDefaultOptions(ProductInterface $product, DataObject $request)
    {
        /** @var AbstractType $typeInstance */
        $typeInstance = $product->getTypeInstance();

        $bundleOptions = [];
        $bundleOptionsQty = [];

        /** @var OptionCollection $optionsCollection */
        $optionsCollection = $typeInstance->getOptionsCollection($product);

        /** @var Option $option */
        foreach ($optionsCollection as $option) {
            /** If the option is not required then we can by pass it. */
            if (!$option->getRequired()) {
                continue;
            }

            /** @var Selection $selection */
            $selection = $option->getDefaultSelection();

            if (!$selection) {
                /** @var SelectionCollection $selections */
                $selection = $typeInstance->getSelectionsCollection(
                    $option->getId(),
                    $product
                )->getFirstItem();
            }

            if (!$selection) {
                continue;
            }

            $bundleOptions[$option->getId()] = $selection->getSelectionId();
        }

        $request->setData('bundle_option', $bundleOptions);
        $request->setData('bundle_option_qty', $bundleOptionsQty);
    }
}
