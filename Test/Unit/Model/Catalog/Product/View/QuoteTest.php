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

namespace Frenet\Shipping\Test\Unit\Model\Catalog\Product\View;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;
use Frenet\Shipping\Model\Calculator;
use Frenet\Shipping\Model\Catalog\Product\View\Quote;
use Frenet\Shipping\Model\Catalog\Product\View\RateRequestBuilder;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\DeliveryTimeCalculatorInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests that the product-page quote service maps Frenet services to rows and returns nothing when the product is gone.
 */
#[AllowMockObjectsWithoutExpectations]
class QuoteTest extends TestCase
{
    /**
     * @var string
     */
    private const POSTCODE = '01310-100';

    /**
     * @var int
     */
    private const WEBSITE_ID = 1;

    /**
     * @var ProductRepositoryInterface&MockObject
     */
    private MockObject $productRepository;

    /**
     * @var RateRequestProviderInterface&MockObject
     */
    private MockObject $rateRequestProvider;

    /**
     * @var Calculator&MockObject
     */
    private MockObject $calculator;

    /**
     * @var RateRequestBuilder&MockObject
     */
    private MockObject $rateRequestBuilder;

    /**
     * @var DeliveryTimeCalculatorInterface&MockObject
     */
    private MockObject $deliveryTimeCalculator;

    /**
     * @var ConfigInterface&MockObject
     */
    private MockObject $config;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private MockObject $storeManager;

    /**
     * @var Quote
     */
    private Quote $subject;

    /**
     * Wires the quote service with mocked collaborators so no real product, cache or API lookup occurs.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->rateRequestProvider = $this->createMock(RateRequestProviderInterface::class);
        $this->calculator = $this->createMock(Calculator::class);
        $this->rateRequestBuilder = $this->createMock(RateRequestBuilder::class);
        $this->deliveryTimeCalculator = $this->createMock(DeliveryTimeCalculatorInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(self::WEBSITE_ID);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->subject = new Quote(
            $this->productRepository,
            $this->rateRequestProvider,
            $this->calculator,
            $this->rateRequestBuilder,
            $this->logger,
            $this->deliveryTimeCalculator,
            $this->config,
            $this->storeManager
        );
    }

    /**
     * Confirms a missing product id degrades to an empty result instead of letting the repository exception escape.
     *
     * @return void
     */
    public function testShouldReturnEmptyArrayWhenTheProductIdDoesNotExist(): void
    {
        $this->productRepository->method('getById')->willThrowException(new NoSuchEntityException());

        $this->assertSame([], $this->subject->quoteByProductId(404, self::POSTCODE));
    }

    /**
     * Confirms the SKU lookup path degrades the same way as the product id lookup path.
     *
     * @return void
     */
    public function testShouldReturnEmptyArrayWhenTheProductSkuDoesNotExist(): void
    {
        $this->productRepository->method('get')->willThrowException(new NoSuchEntityException());

        $this->assertSame([], $this->subject->quoteByProductSku('missing-sku', self::POSTCODE));
    }

    /**
     * Confirms only the non-error service survives the mapping and that its fields land in the expected row shape.
     *
     * @return void
     */
    public function testShouldMapCalculatedServicesIntoRowsWhenQuotingByProductId(): void
    {
        $this->productRepository->method('getById')->willReturn($this->product());
        $this->config->method('isProductQuoteAllowed')->willReturn(true);
        $this->rateRequestBuilder->method('build')->willReturn($this->createMock(RateRequest::class));
        $this->deliveryTimeCalculator->method('calculate')->willReturn(5);
        $this->config->method('getShippingForecastMessage')->willReturn('Em {{d}} dia(s)');
        $this->calculator->method('getQuote')->willReturn([
            $this->service(false, '04510', 'Correios', 'PAC', 25.9),
            $this->service(true, '04014', 'Correios', 'SEDEX', 42.5),
        ]);

        $result = $this->subject->quoteByProductId(1, self::POSTCODE);

        $this->assertSame([[
            'service_code' => '04510',
            'carrier' => 'Correios',
            'message' => '',
            'delivery_time' => 5,
            'delivery_description' => 'Em 5 dia(s)',
            'service_description' => 'PAC',
            'shipping_price' => 25.9,
        ]], $result);
    }

    /**
     * Confirms a disabled product is never quoted, even though the repository still loads it.
     *
     * @return void
     */
    public function testShouldNotQuoteADisabledProduct(): void
    {
        $this->productRepository->method('getById')->willReturn($this->product(Status::STATUS_DISABLED));
        $this->config->method('isProductQuoteAllowed')->willReturn(true);
        $this->calculator->expects($this->never())->method('getQuote');

        $this->assertSame([], $this->subject->quoteByProductId(1, self::POSTCODE));
    }

    /**
     * Confirms a product type left out of the product page quote configuration is never quoted.
     *
     * @return void
     */
    public function testShouldNotQuoteAProductTypeThatIsNotAllowed(): void
    {
        $this->productRepository->method('getById')->willReturn($this->product());
        $this->config->method('isProductQuoteAllowed')->with('simple')->willReturn(false);
        $this->calculator->expects($this->never())->method('getQuote');

        $this->assertSame([], $this->subject->quoteByProductId(1, self::POSTCODE));
    }

    /**
     * Confirms a product that is not assigned to the current website is never quoted.
     *
     * @return void
     */
    public function testShouldNotQuoteAProductOutsideTheCurrentWebsite(): void
    {
        $this->productRepository->method('getById')->willReturn($this->product(Status::STATUS_ENABLED, [2]));
        $this->config->method('isProductQuoteAllowed')->willReturn(true);
        $this->calculator->expects($this->never())->method('getQuote');

        $this->assertSame([], $this->subject->quoteByProductId(1, self::POSTCODE));
    }

    /**
     * Builds a mocked simple product with the given status and website assignment.
     *
     * @param int   $status
     * @param int[] $websiteIds
     *
     * @return Product&MockObject
     */
    private function product(int $status = Status::STATUS_ENABLED, array $websiteIds = [self::WEBSITE_ID]): MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getStatus')->willReturn($status);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getWebsiteIds')->willReturn($websiteIds);

        return $product;
    }

    /**
     * Builds a mocked Frenet service result for use as a Calculator::getQuote() return value.
     *
     * @param bool $isError
     * @param string $code
     * @param string $carrier
     * @param string $description
     * @param float $price
     *
     * @return ServiceInterface&MockObject
     */
    private function service(
        bool $isError,
        string $code,
        string $carrier,
        string $description,
        float $price
    ): ServiceInterface&MockObject {
        $service = $this->createMock(ServiceInterface::class);
        $service->method('isError')->willReturn($isError);
        $service->method('getServiceCode')->willReturn($code);
        $service->method('getCarrier')->willReturn($carrier);
        $service->method('getServiceDescription')->willReturn($description);
        $service->method('getShippingPrice')->willReturn($price);
        $service->method('getMessage')->willReturn('');

        return $service;
    }
}
