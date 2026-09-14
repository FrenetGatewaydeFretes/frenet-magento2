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

namespace Frenet\Shipping\Test\Unit\Model\Quote;

use Frenet\Command\Shipping\QuoteInterface;
use Frenet\Shipping\Model\Quote\CouponProcessor;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that CouponProcessor reads the coupon code off the rate request's own items and never falls back to
 * the checkout session, since it runs from inside shipping-rate collection on every quote and
 * Magento\Checkout\Model\Session::getQuote() throws "Infinite loop detected" when called re-entrantly
 * from there (magento/magento2#34830).
 */
#[AllowMockObjectsWithoutExpectations]
class CouponProcessorTest extends TestCase
{
    /**
     * @var RateRequestProviderInterface&MockObject
     */
    private MockObject $rateRequestProvider;

    /**
     * @var CouponProcessor
     */
    private CouponProcessor $subject;

    protected function setUp(): void
    {
        $this->rateRequestProvider = $this->createMock(RateRequestProviderInterface::class);
        $this->subject = new CouponProcessor($this->rateRequestProvider);
    }

    /**
     * The coupon code must come from a rate request item's own quote.
     *
     * @return void
     */
    public function testShouldReturnTheCouponCodeFromTheRateRequestItemsQuote(): void
    {
        $this->rateRequestProvider->method('getRateRequest')
            ->willReturn($this->rateRequest($this->quoteWithCouponCode('SAVE10')));

        $this->assertSame('SAVE10', $this->subject->getCouponCode());
    }

    /**
     * A defensive edge case (no item carries a quote) must return no coupon code instead of crashing or
     * reaching into the checkout session.
     *
     * @return void
     */
    public function testShouldReturnNullWhenNoRateRequestItemHasAQuote(): void
    {
        $item = $this->createMock(QuoteItem::class);
        $item->method('getQuote')->willReturn(null);

        $rateRequest = new RateRequest();
        $rateRequest->setAllItems([$item]);
        $this->rateRequestProvider->method('getRateRequest')->willReturn($rateRequest);

        $this->assertNull($this->subject->getCouponCode());
    }

    /**
     * The shipping quote command only receives a coupon code when one is actually set on the quote.
     *
     * @return void
     */
    public function testShouldNotSetACouponCodeOnTheServiceQuoteWhenTheQuoteHasNone(): void
    {
        $this->rateRequestProvider->method('getRateRequest')
            ->willReturn($this->rateRequest($this->quoteWithCouponCode(null)));

        $serviceQuote = $this->createMock(QuoteInterface::class);
        $serviceQuote->expects($this->never())->method('setCouponCode');

        $this->subject->applyCouponCode($serviceQuote);
    }

    /**
     * Builds a real (unconstructed) rate request whose single item carries the given quote.
     *
     * @param Quote&MockObject $quote
     *
     * @return RateRequest
     */
    private function rateRequest(MockObject $quote): RateRequest
    {
        $item = $this->createMock(QuoteItem::class);
        $item->method('getQuote')->willReturn($quote);

        $rateRequest = new RateRequest();
        $rateRequest->setAllItems([$item]);

        return $rateRequest;
    }

    /**
     * Builds a Quote mock whose magic getCouponCode() (backed by getData()) returns the given value.
     *
     * @param string|null $couponCode
     *
     * @return Quote&MockObject
     */
    private function quoteWithCouponCode(?string $couponCode): MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();
        $quote->method('getData')->with('coupon_code')->willReturn($couponCode);

        return $quote;
    }
}
