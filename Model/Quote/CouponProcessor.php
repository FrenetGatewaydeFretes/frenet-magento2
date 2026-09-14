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

namespace Frenet\Shipping\Model\Quote;

use Frenet\Command\Shipping\QuoteInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

/**
 * Class QuoteCouponProcessor
 */
class CouponProcessor
{
    public function __construct(private readonly RateRequestProviderInterface $requestProvider)
    {
    }

    /**
     * @param QuoteInterface $quote
     *
     * @return $this
     */
    public function applyCouponCode(QuoteInterface $quote): self
    {
        /** Add coupon code if exists. */
        if ($this->getQuoteCouponCode()) {
            $quote->setCouponCode($this->getQuoteCouponCode());
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCouponCode()
    {
        return $this->getQuoteCouponCode();
    }

    /**
     * @return string|null
     */
    private function getQuoteCouponCode()
    {
        try {
            return $this->getQuote()?->getCouponCode();
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Reads the quote off the rate request's own items instead of the checkout session.
     *
     * @return Quote|null
     * @throws LocalizedException
     */
    private function getQuote(): ?Quote
    {
        foreach ($this->requestProvider->getRateRequest()->getAllItems() as $item) {
            if ($item->getQuote()) {
                return $item->getQuote();
            }
        }

        return null;
    }
}
