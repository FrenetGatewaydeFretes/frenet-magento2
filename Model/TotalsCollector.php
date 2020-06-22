<?php

namespace Frenet\Shipping\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;

/**
 * Class TotalsCollector
 *
 * @package Frenet\Shipping\Model
 */
class TotalsCollector
{
    /**
     * @var array
     */
    private $discounts;

    /**
     * @var array
     */
    private $additions;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    public function __construct(
        CheckoutSession $checkoutSession,
        array $discounts = [],
        array $additions = []
    ) {
        $this->discounts = $discounts;
        $this->additions = $additions;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return array
     */
    public function getDiscounts() : array
    {
        return $this->discounts;
    }

    /**
     * @return array
     */
    public function getAdditions() : array
    {
        return $this->additions;
    }

    /**
     * @param Quote $quote
     *
     * @return float
     */
    public function calculateQuoteDiscounts(Quote $quote = null) : float
    {
        $totalDiscount = 0.0000;
        $quote = $this->getQuote($quote);

        foreach ($this->getDiscounts() as $discount) {
            $totalDiscount += (float) $quote->getData($discount);
        }

        return $totalDiscount;
    }

    /**
     * @param Quote $quote
     *
     * @return float
     */
    public function calculateQuoteAdditions(Quote $quote = null) : float
    {
        $totalAddition = 0.0000;
        $quote = $this->getQuote($quote);

        foreach ($this->getAdditions() as $addition) {
            $totalAddition += (float) $quote->getData($addition);
        }

        return $totalAddition;
    }

    /**
     * @param Quote $quote
     *
     * @return Quote
     */
    private function getQuote(Quote $quote = null)
    {
        if (!$quote) {
            return $this->checkoutSession->getQuote();
        }

        return $quote;
    }
}
