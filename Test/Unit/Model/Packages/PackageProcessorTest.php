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

use Frenet\Command\CommandInterface;
use Frenet\Command\Shipping\Quote as ShippingQuoteCommand;
use Frenet\Command\Shipping\QuoteInterface;
use Frenet\Command\ShippingInterface;
use Frenet\Framework\Data\SerializerInterface;
use Frenet\ObjectType\Entity\Shipping\Quote as ShippingQuoteResult;
use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\Shipping\Model\ApiServiceInterface;
use Frenet\Shipping\Model\CacheManager;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Packages\Package;
use Frenet\Shipping\Model\Packages\PackageItem;
use Frenet\Shipping\Model\Packages\PackageProcessor;
use Frenet\Shipping\Model\Quote\CouponProcessor;
use Frenet\Shipping\Model\Quote\QuoteItemValidatorInterface;
use Frenet\Shipping\Model\TotalsCollector;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote as CartQuote;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PackageProcessor prices a package from its own quote and asks the API once per distinct package.
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
     * @var CacheManager&MockObject
     */
    private MockObject $cacheManager;

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
        $this->cacheManager = $this->createMock(CacheManager::class);

        $rateRequest = $this->createMock(RateRequest::class);
        $this->rateRequestProvider->method('getRateRequest')->willReturn($rateRequest);

        $this->subject = new PackageProcessor(
            $this->quoteItemValidator,
            $this->config,
            $this->apiService,
            $this->rateRequestProvider,
            $this->couponProcessor,
            $this->totalsCollector,
            $this->cacheManager
        );
    }

    /**
     * The item's own quote must reach the totals collector, never the checkout session.
     *
     * @return void
     */
    public function testShouldPriceTheShipmentFromTheItemsQuoteWhenThePackageCarriesOne(): void
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
     * A package whose items carry no quote (a defensive edge case) must skip the totals collector entirely.
     *
     * @return void
     */
    public function testShouldNeverCallTheTotalsCollectorWhenThePackageHasNoQuote(): void
    {
        $cartItem = $this->createMock(QuoteItem::class);
        $cartItem->method('getQuote')->willReturn(null);

        $this->totalsCollector->expects($this->never())->method('calculateQuoteAdditions');
        $this->totalsCollector->expects($this->never())->method('calculateQuoteDiscounts');

        $this->subject->process($this->package($cartItem, 10.0));
    }

    /**
     * Identical packages, such as the equal boxes of a split cart, must reach the Frenet API only once.
     *
     * @return void
     */
    public function testShouldCallTheApiOnceWhenPackagesAreIdentical(): void
    {
        $first = $this->quoteCommand(['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 4]]], $this->once());
        $second = $this->quoteCommand(['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 4]]], $this->never());
        $this->givenQuoteCommands($first, $second);

        $firstResult = $this->subject->process($this->simplePackage());
        $secondResult = $this->subject->process($this->simplePackage());

        $this->assertEquals($firstResult, $secondResult);
        $this->assertNotSame($firstResult[0], $secondResult[0]);
    }

    /**
     * Packages with different content are each sent to the API.
     *
     * @return void
     */
    public function testShouldCallTheApiForEachPackageWhenTheyDiffer(): void
    {
        $first = $this->quoteCommand(['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 4]]], $this->once());
        $second = $this->quoteCommand(['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 1]]], $this->once());
        $this->givenQuoteCommands($first, $second);

        $this->subject->process($this->simplePackage());
        $this->subject->process($this->simplePackage());
    }

    /**
     * Summing one package's price into a returned service must not change what another identical package receives.
     *
     * @return void
     */
    public function testShouldKeepAnIdenticalPackageIntactWhenAnEarlierResultIsChanged(): void
    {
        $payload = ['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 4]]];
        $this->givenQuoteCommands(
            $this->quoteCommand($payload, $this->once()),
            $this->quoteCommand($payload, $this->never())
        );

        $firstResult = $this->subject->process($this->simplePackage());
        $firstResult[0]->setData(Service::FIELD_SHIPPING_PRICE, 999.0);
        $secondResult = $this->subject->process($this->simplePackage());

        $this->assertEqualsWithDelta(148.67, $secondResult[0]->getShippingPrice(), 0.001);
    }

    /**
     * Resetting the state between requests sends an identical package to the API again.
     *
     * @return void
     */
    public function testShouldCallTheApiAgainWhenTheStateIsReset(): void
    {
        $payload = ['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 4]]];
        $this->givenQuoteCommands(
            $this->quoteCommand($payload, $this->once()),
            $this->quoteCommand($payload, $this->once())
        );

        $this->subject->process($this->simplePackage());
        $this->subject->_resetState();
        $this->subject->process($this->simplePackage());
    }

    /**
     * A request already answered in the cache is served from it without calling the API.
     *
     * @return void
     */
    public function testShouldSkipTheApiWhenTheCacheHoldsTheAnswer(): void
    {
        $cached = $this->quoteCommand(['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 4]]], $this->never());
        $this->givenQuoteCommands($cached);
        $this->cacheManager->method('load')->willReturn([$this->correiosService()]);

        $result = $this->subject->process($this->simplePackage());

        $this->assertSame('03298', $result[0]->getServiceCode());
    }

    /**
     * A new request is sent to the API and its answer is kept in the cache under the request key.
     *
     * @return void
     */
    public function testShouldStoreTheAnswerWhenTheApiReplies(): void
    {
        $this->givenQuoteCommands(
            $this->quoteCommand(['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 4]]], $this->once())
        );
        $this->cacheManager->expects($this->once())
            ->method('save')
            ->with($this->matchesRegularExpression('/^[0-9a-f]{64}$/'), $this->countOf(1));

        $this->subject->process($this->simplePackage());
    }

    /**
     * The same package quoted with another store's token is a different request, so the API is asked again.
     *
     * @return void
     */
    public function testShouldAskTheApiAgainWhenTheSamePackageUsesAnotherToken(): void
    {
        $payload = ['ShippingItemArray' => [['SKU' => 'sku-1', 'Quantity' => 4]]];
        $this->givenQuoteCommands(
            $this->quoteCommand($payload, $this->once()),
            $this->quoteCommand($payload, $this->once())
        );
        $this->config->method('getToken')->willReturnOnConsecutiveCalls('website-a-token', 'website-b-token');

        $this->subject->process($this->simplePackage());
        $this->subject->process($this->simplePackage());
    }

    /**
     * A payload that cannot be encoded, such as a SKU with invalid UTF-8, is sent to the API for every package.
     *
     * @return void
     */
    public function testShouldCallTheApiForEachPackageWhenThePayloadCannotBeEncoded(): void
    {
        $payload = ['ShippingItemArray' => [['SKU' => "sku-\xB1", 'Quantity' => 4]]];
        $this->givenQuoteCommands(
            $this->quoteCommand($payload, $this->once()),
            $this->quoteCommand($payload, $this->once())
        );

        $this->subject->process($this->simplePackage());
        $this->subject->process($this->simplePackage());
    }

    /**
     * A quote command that does not expose its payload is sent to the API for every package.
     *
     * @return void
     */
    public function testShouldCallTheApiForEachPackageWhenTheCommandHidesItsPayload(): void
    {
        $this->givenQuoteCommands($this->opaqueQuoteCommand($this->once()), $this->opaqueQuoteCommand($this->once()));

        $this->subject->process($this->simplePackage());
        $this->subject->process($this->simplePackage());
    }

    /**
     * Builds a one-item package whose cart item has no quote, so only the API call is exercised.
     *
     * @return Package
     */
    private function simplePackage(): Package
    {
        $cartItem = $this->createMock(QuoteItem::class);
        $cartItem->method('getQuote')->willReturn(null);

        $packageItem = $this->createMock(PackageItem::class);
        $packageItem->method('getCartItem')->willReturn($cartItem);

        $package = $this->createMock(Package::class);
        $package->method('getItems')->willReturn([$packageItem]);
        $package->method('getTotalPrice')->willReturn(10.0);

        return $package;
    }

    /**
     * Builds a shipping quote command carrying the given payload and expected to reach the API as stated.
     *
     * @param mixed[] $payload
     * @param object  $executions
     *
     * @return ShippingQuoteCommand&MockObject
     */
    private function quoteCommand(array $payload, object $executions): MockObject
    {
        $service = $this->correiosService();

        $command = $this->createMock(ShippingQuoteCommand::class);
        $command->method('setSellerPostcode')->willReturnSelf();
        $command->method('setRecipientPostcode')->willReturnSelf();
        $command->method('setRecipientCountry')->willReturnSelf();
        $command->method('setShipmentInvoiceValue')->willReturnSelf();
        $command->method('addShippingItem')->willReturnSelf();
        $command->method('getData')->willReturn($payload);
        $command->expects($executions)->method('execute')->willReturn(
            $this->createConfiguredMock(ShippingQuoteResult::class, ['getShippingServices' => [$service]])
        );

        return $command;
    }

    /**
     * Builds a shipping quote command without a readable payload, expected to reach the API as stated.
     *
     * @param object $executions
     *
     * @return QuoteInterface&CommandInterface&MockObject
     */
    private function opaqueQuoteCommand(object $executions): MockObject
    {
        $command = $this->createMockForIntersectionOfInterfaces([QuoteInterface::class, CommandInterface::class]);
        $command->method('setSellerPostcode')->willReturnSelf();
        $command->method('setRecipientPostcode')->willReturnSelf();
        $command->method('setRecipientCountry')->willReturnSelf();
        $command->method('setShipmentInvoiceValue')->willReturnSelf();
        $command->method('addShippingItem')->willReturnSelf();
        $command->expects($executions)->method('execute')->willReturn(
            $this->createConfiguredMock(ShippingQuoteResult::class, ['getShippingServices' => []])
        );

        return $command;
    }

    /**
     * Builds the Correios PAC service the stubbed API answers with.
     *
     * @return Service
     */
    private function correiosService(): Service
    {
        return new Service($this->createMock(SerializerInterface::class), [
            'ServiceCode' => '03298',
            'Carrier' => 'Correios',
            'ShippingPrice' => '148.67',
            'Error' => false,
        ]);
    }

    /**
     * Makes the API hand out the given quote commands, one per processed package.
     *
     * @param QuoteInterface ...$commands
     *
     * @return void
     */
    private function givenQuoteCommands(QuoteInterface ...$commands): void
    {
        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('quote')->willReturnOnConsecutiveCalls(...$commands);
        $this->apiService->method('shipping')->willReturn($shipping);
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
