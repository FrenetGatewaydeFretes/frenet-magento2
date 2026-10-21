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
use Frenet\Shipping\Model\ConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Decides whether the product page quote widget shows up and builds the URL its AJAX requests are sent to.
 */
class Quote implements ArgumentInterface
{
    /**
     * @var string
     */
    public const URI_PATH = 'frenet/product/quote';

    /**
     * @var QuoteBlock
     */
    private $block;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }

    /**
     * Keeps the block being rendered so the product and base URL can be read from it.
     *
     * @param QuoteBlock $block
     *
     * @return $this
     */
    public function setBlock(QuoteBlock $block)
    {
        $this->block = $block;
        return $this;
    }

    /**
     * Checks that a product is loaded, the product page quote is enabled and its product type is allowed.
     *
     * @return bool
     */
    public function isProductQuoteAllowed() : bool
    {
        if (!$this->block->getProduct()) {
            return false;
        }

        if (!$this->config->isProductQuoteEnabled()) {
            return false;
        }

        $typeId = $this->block->getProduct()->getTypeId();
        return $this->config->isProductQuoteAllowed($typeId);
    }

    /**
     * Returns the absolute URL of the product quote controller.
     *
     * @return string
     */
    public function getUrl() : string
    {
        return $this->getBaseUrl() . self::URI_PATH;
    }

    /**
     * Returns the store base URL taken from the block being rendered.
     *
     * @return string
     */
    private function getBaseUrl() : string
    {
        return $this->block->getBaseUrl();
    }
}
