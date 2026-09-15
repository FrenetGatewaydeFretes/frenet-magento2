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

namespace Frenet\Shipping\Test\Unit\Model;

use Frenet\Shipping\Model\Totals\CollectorInterface;
use Frenet\Shipping\Model\TotalsCollector;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that TotalsCollector never touches the checkout session while a quote is already at hand.
 */
#[AllowMockObjectsWithoutExpectations]
class TotalsCollectorTest extends TestCase
{
    /**
     * @var CheckoutSession&MockObject
     */
    private MockObject $checkoutSession;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
    }

    /**
     * With no discount/addition collectors configured, the total is zero and the session is never read.
     *
     * @return void
     */
    public function testShouldReturnZeroWithoutTouchingTheSessionWhenNoCollectorsAreConfigured(): void
    {
        $this->checkoutSession->expects($this->never())->method('getQuote');
        $subject = new TotalsCollector($this->checkoutSession, [], []);

        $this->assertSame(0.0, $subject->calculateQuoteDiscounts());
        $this->assertSame(0.0, $subject->calculateQuoteAdditions());
    }

    /**
     * When the caller passes the quote it already has, the configured collectors use it directly.
     *
     * @return void
     */
    public function testShouldUseTheGivenQuoteWithoutTouchingTheSessionWhenACollectorIsConfigured(): void
    {
        $quote = $this->createMock(Quote::class);
        $collector = $this->createMock(CollectorInterface::class);
        $collector->expects($this->once())
            ->method('collect')
            ->with($this->identicalTo($quote))
            ->willReturn(12.5);

        $this->checkoutSession->expects($this->never())->method('getQuote');
        $subject = new TotalsCollector($this->checkoutSession, [], [$collector]);

        $this->assertSame(12.5, $subject->calculateQuoteAdditions($quote));
    }

    /**
     * Only a caller with no quote of its own (outside the rate-collection call chain) falls back to it.
     *
     * @return void
     */
    public function testShouldFallBackToTheSessionQuoteWhenNoQuoteIsGivenAndACollectorIsConfigured(): void
    {
        $quote = $this->createMock(Quote::class);
        $collector = $this->createMock(CollectorInterface::class);
        $collector->method('collect')->with($this->identicalTo($quote))->willReturn(3.0);

        $this->checkoutSession->expects($this->once())->method('getQuote')->willReturn($quote);
        $subject = new TotalsCollector($this->checkoutSession, [$collector], []);

        $this->assertSame(3.0, $subject->calculateQuoteDiscounts());
    }
}
