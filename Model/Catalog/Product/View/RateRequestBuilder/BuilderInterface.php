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
 * Contract for filling the product type options of the buy request used by the product page quote.
 */
interface BuilderInterface
{
    /**
     * Fills the buy request with the type options from the given ones, or with the product defaults.
     *
     * @param ProductInterface $product
     * @param DataObject       $request
     * @param mixed[]          $options
     *
     * @return void
     */
    public function build(ProductInterface $product, DataObject $request, array $options = []);
}
