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

namespace Frenet\Shipping\Model\Quote;

/**
 * Decides whether an overweight cart may be split into several weight-limited Frenet quotes.
 */
interface MultiQuoteValidatorInterface
{
    /**
     * Checks whether the current rate request can be split into several weight-limited packages.
     *
     * @return bool
     */
    public function canProcessMultiQuote() : bool;
}
