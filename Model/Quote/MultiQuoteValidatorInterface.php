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
 * Class MultiQuoteValidatorInterface
 */
interface MultiQuoteValidatorInterface
{
    /**
     * @param Magento\Quote\Model\Quote\Address\RateRequest $rateRequest
     *
     * @return bool
     */
    public function canProcessMultiQuote(\Magento\Quote\Model\Quote\Address\RateRequest $rateRequest);
}
