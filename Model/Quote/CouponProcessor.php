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
     * Magento\Checkout\Model\Session::getQuote() guards against re-entrancy and throws "Infinite loop
     * detected" if it is called again while it is already resolving the quote for the current request
     * (see magento/magento2#34830) - a real risk here since this runs from inside rate collection on
     * every quote. The rate request always carries non-empty, quote-bound items by the time this method
     * is reached (Frenet::processAdditionalValidation() rejects an empty item list beforehand), so no
     * coupon code is returned on the defensive case where none of them do.
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
