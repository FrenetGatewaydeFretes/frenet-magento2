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

use Frenet\Shipping\ViewModel\Catalog\Product\View\Quote as ViewModel;
use Magento\Catalog\Block\Product\View;

/**
 * Renders the product page shipping quote widget and points its JS component at the quote endpoint.
 *
 * @method ViewModel getViewModel()
 */
class Quote extends View
{
    /**
     * Hands this block to the view model so it can read the product and base URL being rendered.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _construct()
    {
        parent::_construct();
        $this->getViewModel()->setBlock($this);
    }

    /**
     * Returns the client-side validation rules applied to the quote form input.
     *
     * @return bool[]
     */
    public function getValidators()
    {
        return [
            'required-number' => true
        ];
    }

    /**
     * @inheritDoc
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _beforeToHtml()
    {
        $this->jsLayout['components']['frenet-quote']['config']['url'] = $this->getViewModel()->getUrl();
        parent::_beforeToHtml();
    }

    /**
     * Renders nothing when the product page quote is disabled or not allowed for the product type.
     *
     * @return string
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _toHtml() : string
    {
        if (!$this->getViewModel()->isProductQuoteAllowed()) {
            return '';
        }

        return parent::_toHtml();
    }
}
