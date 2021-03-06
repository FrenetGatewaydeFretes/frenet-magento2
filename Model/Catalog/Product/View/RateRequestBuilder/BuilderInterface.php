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

interface BuilderInterface
{
    /**
     * @param ProductInterface $product
     * @param DataObject       $request
     * @param array            $options
     *
     * @return void
     */
    public function build(ProductInterface $product, DataObject $request, array $options = []);
}
