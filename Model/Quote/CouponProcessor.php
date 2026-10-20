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

use Exception;
use Frenet\Command\Shipping\QuoteInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

/**
 * Passes the cart coupon to the Frenet quote request, reading it from the quote behind the rate request.
 */
class CouponProcessor
{
    /**
     * @param RateRequestProviderInterface $requestProvider
     */
    public function __construct(private readonly RateRequestProviderInterface $requestProvider)
    {
    }

    /**
     * Sets the cart's coupon code on the Frenet quote request when the cart has one.
     *
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
     * Returns the coupon code applied to the cart behind the current rate request.
     *
     * @return string|null
     */
    public function getCouponCode()
    {
        return $this->getQuoteCouponCode();
    }

    /**
     * Reads the coupon code from the cart, returning null when the cart cannot be read.
     *
     * @return string|null
     */
    private function getQuoteCouponCode()
    {
        try {
            return $this->getQuote()?->getCouponCode();
        } catch (Exception $exception) {
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
