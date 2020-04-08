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

namespace Frenet\Shipping\Model\Quote;

/**
 * Class MultiQuoteValidatorInterface
 *
 * @package Frenet\Shipping\Model\Quote
 */
interface MultiQuoteValidatorInterface
{
    /**
     * @return bool
     */
    public function canProcessMultiQuote() : bool;
}
