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
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\MockObject\MockObject;

class RateRequestProviderTest extends TestCase
{
    /**
     * @var RateRequestProvider
     */
    private $rateRequestProvider;

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
        $this->assertNull($this->rateRequestProvider->getRateRequest());
    }
}
