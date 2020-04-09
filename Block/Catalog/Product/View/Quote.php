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
 * Class Quote
 *
 * @method ViewModel getViewModel
 *
 * @package Frenet\Shipping\Block\Catalog\Product\View
 */
class Quote extends View
{
    protected function _construct()
    {
        parent::_construct();
        $this->getViewModel()->setBlock($this);
    }

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
     * @inheritDoc
     */
    protected function _beforeToHtml()
    {
        $this->jsLayout['components']['frenet-quote']['config']['api_url'] = $this->getViewModel()->getApiUrl();
        parent::_beforeToHtml();
    }
}
