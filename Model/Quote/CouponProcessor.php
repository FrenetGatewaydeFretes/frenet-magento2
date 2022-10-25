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
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Class QuoteCouponProcessor
 */
class CouponProcessor
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var RateRequestProvider
     */
    private $requestProvider;

    /**
     * CouponProcessor constructor.
     *
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        RateRequestProvider $requestProvider
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->requestProvider = $requestProvider;
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
            return $this->getQuote()->getCouponCode();
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getQuote()
    {
        /**
         * For some reason the quote from checkout session was creating a new quote.
         * When this occurs the message "Request Rate is not set" is displayed when placing order.
         * This is a workaround to solve the problem.
         */
        $allItems = $this->requestProvider->getRateRequest()->getAllItems();
        /** @var \Magento\Quote\Model\Quote\Item\AbstractItem $item */
        foreach ($allItems as $item) {
            if ($item->getQuote()) {
                return $item->getQuote();
            }
        }
        return $this->checkoutSession->getQuote();
    }
}
