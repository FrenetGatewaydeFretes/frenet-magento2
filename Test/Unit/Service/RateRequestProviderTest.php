<?php
/**
 * Frenet_Shipping
 *
 * @vendor    Frenet
 * @package   Shipping
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@frenet.com.br>
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Service;

use Frenet\Shipping\Service\RateRequestProvider;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests that RateRequestProvider carries one rate request across the quote flow and fails loudly once it is cleared.
 */
class RateRequestProviderTest extends TestCase
{
    /**
     * @var RateRequestProvider
     */
    private RateRequestProvider $subject;

    protected function setUp(): void
    {
        $this->subject = new RateRequestProvider();
    }

    public function testShouldImplementTheRateRequestProviderContract(): void
    {
        $this->assertInstanceOf(RateRequestProviderInterface::class, $this->subject);
    }

    public function testShouldReturnSelfWhenSettingTheRateRequest(): void
    {
        $this->assertSame(
            $this->subject,
            $this->subject->setRateRequest($this->createStub(RateRequest::class))
        );
    }

    public function testShouldReturnTheStoredRequestWhenOneWasSet(): void
    {
        $rateRequest = $this->createStub(RateRequest::class);
        $rateRequest->method('getData')->willReturn(9.9988);
        $this->subject->setRateRequest($rateRequest);

        $this->assertSame($rateRequest, $this->subject->getRateRequest());
        $this->assertSame(9.9988, $this->subject->getRateRequest()->getData());
    }

    public function testShouldThrowWhenGettingTheRateRequestAfterClear(): void
    {
        $this->subject->setRateRequest($this->createStub(RateRequest::class));
        $this->subject->clear();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Rate Request is not set.');

        $this->subject->getRateRequest();
    }
}
