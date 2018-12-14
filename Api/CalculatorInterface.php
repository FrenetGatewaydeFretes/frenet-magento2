<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Api;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class CalculatorInterface
 * @package Frenet\Shipping\Api
 */
interface CalculatorInterface
{
    /**
     * @param RateRequest $request
     * @return mixed
     */
    public function getQuote(RateRequest $request);
}
