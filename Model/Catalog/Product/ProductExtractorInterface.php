<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Catalog\Product;

use Magento\Catalog\Model\Product;

/**
 * Dimensions extractor bound to a catalog product whose attributes supply the package values.
 */
interface ProductExtractorInterface extends DimensionsExtractorInterface
{
    /**
     * Sets the product to read the values from, ignoring products without an ID or store.
     *
     * @param Product $product
     *
     * @return $this
     */
    public function setProduct(Product $product) : self;
}
