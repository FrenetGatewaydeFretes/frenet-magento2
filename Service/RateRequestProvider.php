<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

namespace Frenet\Shipping\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class RateRequestProvider
 *
 * @package Frenet\Shipping\Service
 */
class RateRequestProvider
{
    /**
     * @var RateRequest
     */
    private $rateRequest;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateRequestFactory
     */
    private $rateRequestFactory;

    public function __construct(
        \Magento\Quote\Model\Quote\Address\RateRequestFactory $rateRequestFactory
    ) {
        $this->rateRequestFactory = $rateRequestFactory;
    }

    /**
     * @return RateRequest
     */
    public function createRateRequest()
    {
        return $this->rateRequestFactory->create();
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return $this
     */
    public function setRateRequest(RateRequest $rateRequest) : self
    {
        $this->rateRequest = $rateRequest;
        return $this;
    }

    /**
     * @return RateRequest
     * @throws LocalizedException
     */
    public function getRateRequest() : RateRequest
    {
        if ($this->rateRequest) {
            return $this->rateRequest;
        }

        throw new LocalizedException(__('Rate Request is not set.'));
    }

    /**
     * @return $this
     */
    public function clear() : self
    {
        $this->rateRequest = null;
        return $this;
    }
}
