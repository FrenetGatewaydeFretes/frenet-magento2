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

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Interface ProductExtractorInterface
 */
interface ProductExtractorInterface extends DimensionsExtractorInterface
{
    /**
     * @param ProductInterface $product
     *
     * @return $this
     */
    public function setProduct(ProductInterface $product) : self;
}
