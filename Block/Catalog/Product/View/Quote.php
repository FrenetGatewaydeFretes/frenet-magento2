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
declare(strict_types=1);

namespace Frenet\Shipping\Block\Catalog\Product\View;

use Magento\Catalog\Block\Product\View;

class Quote extends View
{
    /**
     * @return array
     */
    public function getValidators()
    {
        return [
            'required-number' => true
        ];
    }

    /**
     * @return string
     */
    public function getApiUrl() : string
    {
        $productId = $this->getProduct()->getId();
        return $this->getApiBaseUrl() . "/V1/frenetShipping/quote/productId/{$productId}";
    }

    /**
     * @return string
     */
    private function getApiBaseUrl() : string
    {
        return $this->getBaseUrl() . "/rest";
    }
}
