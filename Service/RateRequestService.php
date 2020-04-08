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

use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class RateRequestService
 *
 * @package Frenet\Shipping\Service
 */
class RateRequestService
{
    /**
     * @var RateRequest
     */
    private $rateRequest;

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
     */
    public function getRateRequest() : RateRequest
    {
        return $this->rateRequest;
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
