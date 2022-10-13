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

namespace Frenet\Shipping\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateRequestFactory;
use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class RateRequestProvider
 *
 * @package Frenet\Shipping\Service
 */
class RateRequestProvider extends FrenetMagentoAbstract
{
    /**
     * @var RateRequest
     */
    private $rateRequest;

    /**
     * @var RateRequestFactory
     */
    private $rateRequestFactory;

    /**
     * @param RateRequestFactory        $rateRequestFactory
     * @param \Psr\Log\LoggerInterface  $logger
     */
    public function __construct(
        RateRequestFactory $rateRequestFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
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
    public function setRateRequest(RateRequest $rateRequest): self
    {
        $this->rateRequest = $rateRequest;
        return $this;
    }

    /**
     * @return RateRequest
     * @throws LocalizedException
     */
    public function getRateRequest(): RateRequest
    {
        $this->_logger->debug("rate-request-pre-getRateRequest: ");//.var_export($this->rateRequestProvider, true));
        if ($this->rateRequest) {
            return $this->rateRequest;
        }

        throw new LocalizedException(__('Rate Request is not set.'));
    }

    /**
     * @return $this
     */
    public function clear(): self
    {
        $this->rateRequest = null;
        return $this;
    }
}
