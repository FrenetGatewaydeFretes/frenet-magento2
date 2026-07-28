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

namespace Frenet\Shipping\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;

/**
 * Class TotalsCollector
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
        return $this->iterateCollectors($this->getDiscounts(), $quote);
    }

    /**
     * @param Quote $quote
     *
     * @return float
     */
    public function calculateQuoteAdditions(Quote $quote = null) : float
    {
        return $this->iterateCollectors($this->getAdditions(), $quote);
    }

    /**
     * @param array      $collectors
     * @param Quote|null $quote
     *
     * @return float
     */
    private function iterateCollectors(array $collectors = [], Quote $quote = null) : float
    {
        $total = 0.0000;
        $quote = $this->getQuote($quote);

        /** @var Totals\CollectorInterface $collector */
        foreach ($collectors as $collector) {
            $total += (float) $collector->collect($quote);
        }
        return $total;
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
