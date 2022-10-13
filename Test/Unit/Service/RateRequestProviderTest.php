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

namespace Frenet\Shipping\Test\Unit\Service;

use Frenet\Shipping\Service\RateRequestProvider;
use Frenet\Shipping\Test\Unit\TestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\MockObject\MockObject;
use \Psr\Log\LoggerInterface;

class RateRequestProviderTest extends TestCase
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    /**
     * @var RateRequestProvider
     */
    private $rateRequestProvider;

    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger = $logger;
    }

    protected function setUp()
    {
        $this->rateRequestProvider = $this->getObject(RateRequestProvider::class);
    }

    /**
     * @test
     */
    public function setRateRequest()
    {
        /** @var RateRequest | MockObject $rateRequest */
        $rateRequest = $this->createMock(RateRequest::class);
        $this->assertInstanceOf(
            RateRequestProvider::class,
            $this->rateRequestProvider->setRateRequest($rateRequest)
        );
    }

    /**
     * @test
     */
    public function getRateRequest()
    {
        $this->_logger->debug("rate-request-provider-pre-getRateRequest: ");//.var_export($this->rateRequestProvider, true));
        /** @var RateRequest | MockObject $rateRequest */
        $rateRequest = $this->createMock(RateRequest::class);
        $rateRequest->method('getData')->willReturn(9.9988);
        $this->rateRequestProvider->setRateRequest($rateRequest);
        $this->assertInstanceOf(
            RateRequest::class,
            $this->rateRequestProvider->getRateRequest()
        );
        $this->assertEquals(
            9.9988,
            $this->rateRequestProvider->getRateRequest()->getData()
        );
    }

    /**
     * @test
     */
    public function clear()
    {
        /** @var RateRequest | MockObject $rateRequest */
        $rateRequest = $this->createMock(RateRequest::class);
        $this->rateRequestProvider->setRateRequest($rateRequest);
        $this->rateRequestProvider->clear();
        $this->expectExceptionObject($this->getObject(LocalizedException::class));
        $this->expectExceptionMessage('Rate Request is not set.');
        $this->rateRequestProvider->getRateRequest();
    }
}
