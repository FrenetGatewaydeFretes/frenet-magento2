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

namespace Frenet\Shipping\Api;

interface QuoteProductInterface
{
    /**
     * @param int    $id
     * @param string $postcode
     * @param int    $qty
     *
     * @return array
     */
    public function quoteByProductId(int $id, string $postcode, int $qty = 1) : array;

    /**
     * @param string $sku
     * @param string $postcode
     * @param int    $qty
     *
     * @return array
     */
    public function quoteByProductSku(string $sku, string $postcode, int $qty = 1) : array;
}