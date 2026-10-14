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

namespace Frenet\Shipping\Test\Unit\Model\Carrier;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface as QuoteServiceInterface;
use Frenet\Shipping\Model\Carrier\Frenet;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\DeliveryTimeCalculatorInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result as RateResult;
use Magento\Shipping\Model\Rate\ResultFactory as RateResultFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests the Frenet carrier gate methods: the advertised code and the pre-flight checks that gate rate collection.
 */
#[AllowMockObjectsWithoutExpectations]
class FrenetTest extends TestCase
{
    /**
     * @var ConfigInterface&MockObject
     */
    private MockObject $config;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private MockObject $storeManager;

    /**
     * @var Frenet
     */
    private Frenet $subject;

    /**
     * Builds the carrier without its 24-dependency constructor, wiring only the collaborators the gate methods use.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        // The carrier extends AbstractCarrierOnline (24 framework dependencies); build it without the
        // constructor and inject only the collaborators these gate methods actually touch.
        $this->subject = (new ReflectionClass(Frenet::class))->newInstanceWithoutConstructor();
        $this->setDependency('config', $this->config);
        $this->setDependency('storeManagement', $this->storeManager);
    }

    /**
     * Confirms the carrier advertises the code Magento uses to route rate requests to it.
     *
     * @return void
     */
    public function testShouldReportItsCarrierCode(): void
    {
        $this->assertSame(Frenet::CARRIER_CODE, $this->subject->getCarrierCode());
    }

    /**
     * Confirms the allowed-methods map keys the configured title by the carrier code, as core shipping expects.
     *
     * @return void
     */
    public function testShouldExposeTheCarrierCodeInTheAllowedMethods(): void
    {
        $this->config->method('getCarrierConfig')->with('name')->willReturn('frenet');

        $this->assertSame([Frenet::CARRIER_CODE => 'frenet'], $this->subject->getAllowedMethods());
    }

    /**
     * Confirms the carrier does not offer label creation, since it has no label service behind it.
     *
     * @return void
     */
    public function testShouldNotOfferShippingLabels(): void
    {
        $this->assertFalse($this->subject->isShippingLabelsAvailable());
    }

    /**
     * Confirms the inactive flag alone is enough to skip rate collection, before any other check runs.
     *
     * @return void
     */
    public function testShouldNotCollectRatesWhenTheCarrierIsInactive(): void
    {
        $this->config->method('isActive')->willReturn(false);

        $this->assertFalse($this->subject->canCollectRates());
    }

    /**
     * Confirms the carrier proceeds once active with both an origin postcode and a token configured.
     *
     * @return void
     */
    public function testShouldCollectRatesWhenActiveWithAnOriginPostcodeAndToken(): void
    {
        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getOriginPostcode')->willReturn('80010-030');
        $this->config->method('getToken')->willReturn('a-frenet-token');
        $this->storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        $this->assertTrue($this->subject->canCollectRates());
    }

    /**
     * Confirms a rate is still built, without the forecast suffix, when the shipping forecast is turned off.
     *
     * @return void
     */
    public function testShouldBuildRatesWhenTheShippingForecastIsDisabled(): void
    {
        $this->config->method('canShowShippingForecast')->willReturn(false);

        $methods = $this->prepareResultFor($this->createQuoteService('03298', 'PAC', 'Correios', 34.03));

        $this->assertCount(1, $methods);
        $this->assertSame('03298', $methods[0]->getMethod());
        $this->assertSame('PAC', $methods[0]->getMethodTitle());
        $this->assertSame('Correios - PAC', $methods[0]->getMethodDescription());
        $this->assertSame(34.03, $methods[0]->getPrice());
    }

    /**
     * Confirms the configured forecast message is appended to the title and description when the forecast is on.
     *
     * @return void
     */
    public function testShouldAppendTheShippingForecastWhenItIsEnabled(): void
    {
        $this->config->method('canShowShippingForecast')->willReturn(true);
        $this->config->method('getShippingForecastMessage')->willReturn('{{d}} dia(s)');

        $methods = $this->prepareResultFor($this->createQuoteService('03298', 'PAC', 'Correios', 34.03));

        $this->assertSame('PAC - 5 dia(s)', $methods[0]->getMethodTitle());
        $this->assertSame('Correios - PAC - 5 dia(s)', $methods[0]->getMethodDescription());
    }

    /**
     * Runs the carrier's private result builder over the given services and returns the appended rate methods.
     *
     * @param QuoteServiceInterface ...$services
     *
     * @return Method[]
     */
    private function prepareResultFor(QuoteServiceInterface ...$services): array
    {
        $methods = [];
        $result = $this->createMock(RateResult::class);
        $result->method('append')->willReturnCallback(function (Method $method) use (&$methods, $result) {
            $methods[] = $method;

            return $result;
        });

        $rateFactory = $this->createMock(RateResultFactory::class);
        $rateFactory->method('create')->willReturn($result);

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnArgument(0);
        $methodFactory = $this->createMock(MethodFactory::class);
        $methodFactory->method('create')->willReturnCallback(fn () => new Method($priceCurrency));

        $deliveryTimeCalculator = $this->createMock(DeliveryTimeCalculatorInterface::class);
        $deliveryTimeCalculator->method('calculate')->willReturn(5);

        $this->config->method('getCarrierConfig')->willReturn('Frenet Shipping Gateway');
        $this->setDependency('_rateFactory', $rateFactory);
        $this->setDependency('_rateMethodFactory', $methodFactory);
        $this->setDependency('deliveryTimeCalculator', $deliveryTimeCalculator);

        (new ReflectionClass(Frenet::class))->getMethod('prepareResult')->invoke($this->subject, $services);

        return $methods;
    }

    /**
     * Builds a successful Frenet quote service stub.
     *
     * @param string $code
     * @param string $description
     * @param string $carrier
     * @param float  $price
     *
     * @return QuoteServiceInterface&MockObject
     */
    private function createQuoteService(string $code, string $description, string $carrier, float $price): MockObject
    {
        $service = $this->createMock(QuoteServiceInterface::class);
        $service->method('isError')->willReturn(false);
        $service->method('getServiceCode')->willReturn($code);
        $service->method('getServiceDescription')->willReturn($description);
        $service->method('getCarrier')->willReturn($carrier);
        $service->method('getMessage')->willReturn('');
        $service->method('getShippingPrice')->willReturn($price);

        return $service;
    }

    /**
     * Injects a collaborator straight into the carrier's private property, bypassing its skipped constructor.
     *
     * @param string $property
     * @param object $value
     *
     * @return void
     */
    private function setDependency(string $property, object $value): void
    {
        $reflectionProperty = (new ReflectionClass(Frenet::class))->getProperty($property);
        $reflectionProperty->setValue($this->subject, $value);
    }
}
