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

namespace Frenet\Shipping\ViewModel\Catalog\Product\View;

use Frenet\Shipping\Block\Catalog\Product\View\Quote as QuoteBlock;

/**
 * Class Quote
 *
 * @package Frenet\Shipping\ViewModel\Catalog\Product\View
 */
class Quote implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var string
     */
    const URI_PATH = '/frenetShipping/quote/productId';

    /**
     * @var QuoteBlock
     */
    private $block;

    public function setBlock(QuoteBlock $block)
    {
        $this->block = $block;
        return $this;
    }

    /**
     * @return string
     */
    public function getApiUrl() : string
    {
        return $this->getApiBaseUrl() . self::URI_PATH . "/{$this->block->getProduct()->getId()}";
    }

    /**
     * @return string
     */
    private function getApiBaseUrl() : string
    {
        return $this->block->getBaseUrl() . "rest/V1";
    }
}