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

use Frenet\Shipping\Model\Totals\CollectorInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;

/**
 * Sums the amounts that the injected collectors subtract from or add to a quote total.
 */
class TotalsCollector
{
    /**
     * @var CollectorInterface[]
     */
    private $discounts;

    /**
     * @var CollectorInterface[]
     */
    private $additions;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param CheckoutSession      $checkoutSession
     * @param CollectorInterface[] $discounts
     * @param CollectorInterface[] $additions
     */
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
     * Returns the collectors whose amounts are subtracted from the quote total.
     *
     * @return CollectorInterface[]
     */
    public function getDiscounts() : array
    {
        return $this->discounts;
    }

    /**
     * Returns the collectors whose amounts are added to the quote total.
     *
     * @return CollectorInterface[]
     */
    public function getAdditions() : array
    {
        return $this->additions;
    }

    /**
     * Sums the discount collectors for the given quote, or the checkout session quote when none is given.
     *
     * @param Quote|null $quote
     *
     * @return float
     */
    public function calculateQuoteDiscounts(?Quote $quote = null) : float
    {
        return $this->iterateCollectors($this->getDiscounts(), $quote);
    }

    /**
     * Sums the addition collectors for the given quote, or the checkout session quote when none is given.
     *
     * @param Quote|null $quote
     *
     * @return float
     */
    public function calculateQuoteAdditions(?Quote $quote = null) : float
    {
        return $this->iterateCollectors($this->getAdditions(), $quote);
    }

    /**
     * Adds up what each collector returns for the quote, skipping the session lookup when there are no collectors.
     *
     * @param CollectorInterface[] $collectors
     * @param Quote|null           $quote
     *
     * @return float
     */
    private function iterateCollectors(array $collectors = [], ?Quote $quote = null) : float
    {
        if (!$collectors) {
            return 0.0000;
        }

        $total = 0.0000;
        $quote = $this->getQuote($quote);

        /** @var Totals\CollectorInterface $collector */
        foreach ($collectors as $collector) {
            $total += (float) $collector->collect($quote);
        }
        return $total;
    }

    /**
     * Falls back to the checkout session's quote when the caller has none at hand.
     *
     * @param Quote $quote
     *
     * @return Quote
     */
    private function getQuote(?Quote $quote = null)
    {
        if (!$quote) {
            return $this->checkoutSession->getQuote();
        }
        return $quote;
    }
}
