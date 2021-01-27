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
 * Class BundleBuilder
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
     * @param ProductInterface $product
     * @param DataObject       $request
     *
     * @return void
     */
    private function buildDefaultOptions(ProductInterface $product, DataObject $request)
    {
        /** @var \Magento\Catalog\Model\Product\Type\AbstractType $typeInstance */
        $typeInstance = $product->getTypeInstance();

        $bundleOptions = [];
        $bundleOptionsQty = [];

        /** @var \Magento\Bundle\Model\ResourceModel\Option\Collection $optionsCollection */
        $optionsCollection = $typeInstance->getOptionsCollection($product);

        /** @var \Magento\Bundle\Model\Option $option */
        foreach ($optionsCollection as $option) {
            /** If the option is not required then we can by pass it. */
            if (!$option->getRequired()) {
                continue;
            }

            /** @var \Magento\Bundle\Model\Selection $selection */
            $selection = $option->getDefaultSelection();

            if (!$selection) {
                /** @var \Magento\Bundle\Model\ResourceModel\Selection\Collection $selections */
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
