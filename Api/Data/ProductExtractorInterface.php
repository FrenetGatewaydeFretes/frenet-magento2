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

declare(strict_types = 1);

namespace Frenet\Shipping\Api\Data;

/**
 * Class ProductExtractorInterface
 *
 * @package Frenet\Shipping\Api\Data
 */
interface ProductExtractorInterface extends DimensionsExtractorInterface
{
    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     *
     * @return $this
     */
    public function setProduct(\Magento\Catalog\Api\Data\ProductInterface $product);
}
