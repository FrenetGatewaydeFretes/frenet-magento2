<?php

namespace Frenet\Shipping\Model\Quote;

use Frenet\Command\Shipping\QuoteInterface;

/**
 * Class QuoteCouponProcessor
 *
 * @package Frenet\Shipping\Model\Packages
 */
class CouponProcessor
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param QuoteInterface $quote
     *
     * @return $this
     */
    public function applyCouponCode(QuoteInterface $quote) : self
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
    public function getCouponCode() : ?string
    {
        return $this->getQuoteCouponCode();
    }

    /**
     * @return string|null
     */
    private function getQuoteCouponCode() : ?string
    {
        try {
            return $this->checkoutSession->getQuote()->getCouponCode();
        } catch (\Exception $exception) {
            return null;
        }
    }
}