<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Catalog\Product\View;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;
use Frenet\Shipping\Model\Calculator;
use Frenet\Shipping\Model\Catalog\Product\View\Quote;
use Frenet\Shipping\Model\Catalog\Product\View\RateRequestBuilder;
use Frenet\Shipping\Model\Config;
use Frenet\Shipping\Model\DeliveryTimeCalculator;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests that the product-page quote service turns Frenet calculation results into plain rows and stays quiet when the product is gone.
 */
#[AllowMockObjectsWithoutExpectations]
class QuoteTest extends TestCase
{
    private const POSTCODE = '01310-100';

    private ProductRepositoryInterface&MockObject $productRepository;
    private RateRequestProvider&MockObject $rateRequestProvider;
    private Calculator&MockObject $calculator;
    private RateRequestBuilder&MockObject $rateRequestBuilder;
    private DeliveryTimeCalculator&MockObject $deliveryTimeCalculator;
    private Config&MockObject $config;
    private Quote $subject;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->rateRequestProvider = $this->createMock(RateRequestProvider::class);
        $this->calculator = $this->createMock(Calculator::class);
        $this->rateRequestBuilder = $this->createMock(RateRequestBuilder::class);
        $this->deliveryTimeCalculator = $this->createMock(DeliveryTimeCalculator::class);
        $this->config = $this->createMock(Config::class);

        $this->subject = new Quote(
            $this->productRepository,
            $this->rateRequestProvider,
            $this->calculator,
            $this->rateRequestBuilder,
            $this->createMock(LoggerInterface::class),
            $this->deliveryTimeCalculator,
            $this->config
        );
    }

    public function testShouldReturnEmptyArrayWhenTheProductIdDoesNotExist(): void
    {
        $this->productRepository->method('getById')->willThrowException(new NoSuchEntityException());

        $this->assertSame([], $this->subject->quoteByProductId(404, self::POSTCODE));
    }

    public function testShouldReturnEmptyArrayWhenTheProductSkuDoesNotExist(): void
    {
        $this->productRepository->method('get')->willThrowException(new NoSuchEntityException());

        $this->assertSame([], $this->subject->quoteByProductSku('missing-sku', self::POSTCODE));
    }

    public function testShouldMapCalculatedServicesIntoRowsWhenQuotingByProductId(): void
    {
        $this->productRepository->method('getById')->willReturn($this->createMock(ProductInterface::class));
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
