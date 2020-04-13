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

namespace Frenet\Shipping\Model\Catalog\Product\View;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Class QuoteInterface
 */
interface QuoteInterface
{
    /**
     * @param ProductInterface $product
     *
     * @return array
     */
    public function quote(ProductInterface $product) : array;

    /**
     * @param int $productId
     *
     * @return array
     */
    public function quoteByProductId(int $productId) : array;

    /**
     * @param string $productSku
     *
     * @return array
     */
    public function quoteByProductSku(string $productSku) : array;
}
