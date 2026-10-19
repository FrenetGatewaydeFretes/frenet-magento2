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
use Magento\Framework\DataObject;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;

/**
 * Fills the grouped quantities of the buy request, defaulting to each associated product's quantity or one.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
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

        if ($product instanceof Product) {
            $this->buildDefaultOptions($product, $request);
        }
    }

    /**
     * Requests every associated product with its default quantity, or one when it has none.
     *
     * @param Product          $product
     * @param DataObject       $request
     *
     * @return void
     */
    private function buildDefaultOptions(Product $product, DataObject $request)
    {
        /** @var GroupedType $typeInstance */
        $typeInstance = $product->getTypeInstance();

        $associatedProductsQty = [];

        /** @var Product $associatedProduct */
        foreach ($typeInstance->getAssociatedProducts($product) as $associatedProduct) {
            $associatedProductsQty[$associatedProduct->getId()] = $associatedProduct->getQty() ?: 1;
        }

        $request->setData('super_group', $associatedProductsQty);
    }
}
