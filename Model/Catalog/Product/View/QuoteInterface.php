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
 * Contract for quoting shipping on the product page from a product instance, ID or SKU.
 */
interface QuoteInterface
{
    /**
     * Returns the shipping services available for the given product.
     *
     * @param ProductInterface $product
     *
     * @return mixed[][]
     */
    public function quote(ProductInterface $product) : array;

    /**
     * Loads the product by ID and returns the shipping services available for it.
     *
     * @param int $productId
     *
     * @return mixed[][]
     */
    public function quoteByProductId(int $productId) : array;

    /**
     * Loads the product by SKU and returns the shipping services available for it.
     *
     * @param string $productSku
     *
     * @return mixed[][]
     */
    public function quoteByProductSku(string $productSku) : array;
}
