<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

namespace Frenet\Shipping\Api;

/**
 * Service contract for quoting Frenet shipping rates for a single product before it is added to the cart.
 */
interface QuoteProductInterface
{
    /**
     * Quotes the shipping rates of the product with the given ID to the postcode, ready for the storefront.
     *
     * @param int     $id
     * @param string  $postcode
     * @param int     $qty
     * @param mixed[] $options
     *
     * @return mixed[]
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function quoteByProductId(int $id, string $postcode, int $qty = 1, array $options = []) : array;

    /**
     * Quotes the shipping rates of the product with the given SKU to the postcode, ready for the storefront.
     *
     * @param string  $sku
     * @param string  $postcode
     * @param int     $qty
     * @param mixed[] $options
     *
     * @return mixed[]
     */
    public function quoteByProductSku(string $sku, string $postcode, int $qty = 1, array $options = []) : array;
}
