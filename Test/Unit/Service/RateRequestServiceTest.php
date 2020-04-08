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

use Frenet\Shipping\Service\RateRequestService;
use Frenet\Shipping\Test\Unit\TestCase;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\MockObject\MockObject;

class RateRequestServiceTest extends TestCase
{
    /**
     * @var RateRequestService
     */
    private $rateRequestService;

    protected function setUp()
    {
        $this->rateRequestService = $this->getObject(RateRequestService::class);
    }

    /**
     * @test
     */
    public function setRateRequest()
    {
        /** @var RateRequest | MockObject $rateRequest */
        $rateRequest = $this->createMock(RateRequest::class);
        $this->assertInstanceOf(
            RateRequestService::class,
            $this->rateRequestService->setRateRequest($rateRequest)
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
        $this->rateRequestService->setRateRequest($rateRequest);
        $this->assertInstanceOf(
            RateRequest::class,
            $this->rateRequestService->getRateRequest()
        );
        $this->assertEquals(
            9.9988,
            $this->rateRequestService->getRateRequest()->getData()
        );
    }

    /**
     * @test
     */
    public function clear()
    {
        /** @var RateRequest | MockObject $rateRequest */
        $rateRequest = $this->createMock(RateRequest::class);
        $this->rateRequestService->setRateRequest($rateRequest);
        $this->rateRequestService->clear();
        $this->assertNull($this->rateRequestService->getRateRequest());
    }
}
