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

class GroupedBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(ProductInterface $product, DataObject $request, array $options = [])
    {
        if ($options && isset($options['super_group'])) {
            $request->setData('super_group', $options['super_group']);
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

        $associatedProductsQty = [];

        /** @var \Magento\Catalog\Model\Product $associatedProduct */
        foreach ($typeInstance->getAssociatedProducts($product) as $associatedProduct) {
            $associatedProductsQty[$associatedProduct->getId()] = $associatedProduct->getQty() ?: 1;
        }

        $request->setData('super_group', $associatedProductsQty);
    }
}
