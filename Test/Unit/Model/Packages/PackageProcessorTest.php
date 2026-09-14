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

namespace Frenet\Shipping\Test\Unit\Model\Packages;

use Frenet\Command\Shipping\Quote as ShippingQuoteCommand;
use Frenet\Command\ShippingInterface;
use Frenet\ObjectType\Entity\Shipping\Quote as ShippingQuoteResult;
use Frenet\Shipping\Model\ApiServiceInterface;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Packages\Package;
use Frenet\Shipping\Model\Packages\PackageItem;
use Frenet\Shipping\Model\Packages\PackageProcessor;
use Frenet\Shipping\Model\Quote\CouponProcessor;
use Frenet\Shipping\Model\Quote\QuoteItemValidatorInterface;
use Frenet\Shipping\Model\TotalsCollector;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote as CartQuote;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PackageProcessor prices a package from the package's own quote, never the checkout session.
 */
#[AllowMockObjectsWithoutExpectations]
class PackageProcessorTest extends TestCase
{
    /**
     * @var QuoteItemValidatorInterface&MockObject
     */
    private MockObject $quoteItemValidator;

    /**
     * @var ConfigInterface&MockObject
     */
    private MockObject $config;

    /**
     * @var ApiServiceInterface&MockObject
     */
    private MockObject $apiService;

    /**
     * @var RateRequestProviderInterface&MockObject
     */
    private MockObject $rateRequestProvider;

    /**
     * @var CouponProcessor&MockObject
     */
    private MockObject $couponProcessor;

    /**
     * @var TotalsCollector&MockObject
     */
    private MockObject $totalsCollector;

    /**
     * @var PackageProcessor
     */
    private PackageProcessor $subject;

    protected function setUp(): void
    {
        $this->quoteItemValidator = $this->createMock(QuoteItemValidatorInterface::class);
        $this->quoteItemValidator->method('validate')->willReturn(true);

        $this->config = $this->createMock(ConfigInterface::class);
        $this->apiService = $this->createMock(ApiServiceInterface::class);
        $this->rateRequestProvider = $this->createMock(RateRequestProviderInterface::class);
        $this->couponProcessor = $this->createMock(CouponProcessor::class);
        $this->totalsCollector = $this->createMock(TotalsCollector::class);

        $rateRequest = $this->createMock(RateRequest::class);
        $this->rateRequestProvider->method('getRateRequest')->willReturn($rateRequest);

        $this->subject = new PackageProcessor(
            $this->quoteItemValidator,
            $this->config,
            $this->apiService,
            $this->rateRequestProvider,
            $this->couponProcessor,
            $this->totalsCollector
        );
    }

    /**
     * The item's own quote must reach the totals collector, so it never falls back to the checkout
     * session while shipping-rate collection is already in progress on it (magento/magento2#34830).
     *
     * @return void
     */
    public function testShouldPriceTheShipmentFromThePackageItemsQuoteInsteadOfTheCheckoutSession(): void
    {
        $quote = $this->createMock(CartQuote::class);
        $cartItem = $this->createMock(QuoteItem::class);
        $cartItem->method('getQuote')->willReturn($quote);

        $this->totalsCollector->expects($this->once())
            ->method('calculateQuoteAdditions')
            ->with($this->identicalTo($quote))
            ->willReturn(0.0);
        $this->totalsCollector->expects($this->once())
            ->method('calculateQuoteDiscounts')
            ->with($this->identicalTo($quote))
            ->willReturn(0.0);

        $this->subject->process($this->package($cartItem, 34.9));
    }

    /**
     * A package whose items carry no quote (a defensive edge case) must not crash pricing the shipment.
     *
     * @return void
     */
    public function testShouldToleratePackageItemsWithNoQuote(): void
    {
        $cartItem = $this->createMock(QuoteItem::class);
        $cartItem->method('getQuote')->willReturn(null);

        $this->totalsCollector->expects($this->once())
            ->method('calculateQuoteAdditions')
            ->with($this->isNull())
            ->willReturn(0.0);

        $this->subject->process($this->package($cartItem, 10.0));
    }

    /**
     * Builds a package with a single valid item wrapping the given cart item, wired to a stubbed API call.
     *
     * @param QuoteItem&MockObject $cartItem
     * @param float                $totalPrice
     *
     * @return Package
     */
    private function package(MockObject $cartItem, float $totalPrice): Package
    {
        $packageItem = $this->createMock(PackageItem::class);
        $packageItem->method('getCartItem')->willReturn($cartItem);
        $packageItem->method('getSku')->willReturn('sku-1');
        $packageItem->method('getQty')->willReturn(1.0);
        $packageItem->method('getWeight')->willReturn(1.0);
        $packageItem->method('getLength')->willReturn(16.0);
        $packageItem->method('getHeight')->willReturn(2.0);
        $packageItem->method('getWidth')->willReturn(11.0);
        $packageItem->method('getProductCategories')->willReturn('Gear');
        $packageItem->method('isProductFragile')->willReturn(false);

        $package = $this->createMock(Package::class);
        $package->method('getItems')->willReturn([$packageItem]);
        $package->method('getTotalPrice')->willReturn($totalPrice);

        $shippingQuoteCommand = $this->createMock(ShippingQuoteCommand::class);
        $shippingQuoteCommand->method('setSellerPostcode')->willReturnSelf();
        $shippingQuoteCommand->method('setRecipientPostcode')->willReturnSelf();
        $shippingQuoteCommand->method('setRecipientCountry')->willReturnSelf();
        $shippingQuoteCommand->method('setShipmentInvoiceValue')->willReturnSelf();
        $shippingQuoteCommand->method('addShippingItem')->willReturnSelf();
        $shippingQuoteCommand->method('execute')->willReturn(
            $this->createConfiguredMock(ShippingQuoteResult::class, ['getShippingServices' => []])
        );

        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('quote')->willReturn($shippingQuoteCommand);
        $this->apiService->method('shipping')->willReturn($shipping);

        return $package;
    }
}
