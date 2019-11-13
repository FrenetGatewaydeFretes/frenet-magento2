<?php

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
     * @param Magento\Quote\Model\Quote\Address\RateRequest $rateRequest
     *
     * @return bool
     */
    public function canProcessMultiQuote(\Magento\Quote\Model\Quote\Address\RateRequest $rateRequest);
}
