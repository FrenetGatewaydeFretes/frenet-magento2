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

    /**
     * Builds a fresh provider holding no request, the state expected right after DI instantiation.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->subject = new RateRequestProvider();
    }

    /**
     * Asserts the concrete class satisfies the interface its consumers are wired against.
     *
     * @return void
     */
    public function testShouldImplementTheRateRequestProviderContract(): void
    {
        $this->assertInstanceOf(RateRequestProviderInterface::class, $this->subject);
    }

    /**
     * Confirms the fluent return value so callers can chain further calls.
     *
     * @return void
     */
    public function testShouldReturnSelfWhenSettingTheRateRequest(): void
    {
        $this->assertSame(
            $this->subject,
            $this->subject->setRateRequest($this->createStub(RateRequest::class))
        );
    }

    /**
     * Confirms the exact instance set is handed back untouched.
     *
     * @return void
     */
    public function testShouldReturnTheStoredRequestWhenOneWasSet(): void
    {
        $rateRequest = $this->createStub(RateRequest::class);
        $rateRequest->method('getData')->willReturn(9.9988);
        $this->subject->setRateRequest($rateRequest);

        $this->assertSame($rateRequest, $this->subject->getRateRequest());
        $this->assertSame(9.9988, $this->subject->getRateRequest()->getData());
    }

    /**
     * Confirms clear() resets state so a later read fails loudly instead of returning stale data.
     *
     * @return void
     */
    public function testShouldThrowWhenGettingTheRateRequestAfterClear(): void
    {
        $this->subject->setRateRequest($this->createStub(RateRequest::class));
        $this->subject->clear();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Rate Request is not set.');

        $this->subject->getRateRequest();
    }
}
