<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Holds the rate request for the current quote so collaborators down the calculation chain can read it without re-plumbing it.
 */
class RateRequestProvider
{
    private ?RateRequest $rateRequest = null;

    /**
     * Stores the rate request for the current quote.
     *
     * @param RateRequest $rateRequest
     *
     * @return $this
     */
    public function setRateRequest(RateRequest $rateRequest): self
    {
        $this->rateRequest = $rateRequest;
        return $this;
    }

    /**
     * Returns the rate request set for the current quote.
     *
     * @return RateRequest
     * @throws LocalizedException
     */
    public function getRateRequest(): RateRequest
    {
        if ($this->rateRequest) {
            return $this->rateRequest;
        }

        throw new LocalizedException(__('Rate Request is not set.'));
    }

    /**
     * Drops the stored rate request once the quote is done.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->rateRequest = null;
        return $this;
    }
}
