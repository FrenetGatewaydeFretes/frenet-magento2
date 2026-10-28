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

use Frenet\Framework\Data\SerializerInterface;
use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\ObjectType\Entity\Shipping\Quote\ServiceFactory;
use Frenet\Shipping\Model\Packages\Package;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Model\Packages\PackageManager;
use Frenet\Shipping\Model\Packages\PackageMatching;
use Frenet\Shipping\Model\Packages\PackageProcessor;
use Frenet\Shipping\Model\Packages\PackagesCalculator;
use Frenet\Shipping\Model\Quote\MultiQuoteValidatorInterface;
use Frenet\Shipping\Model\WeightConverterInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PackagesCalculator returns a flat Service list for split carts and keeps Multi Quote results per package.
 */
#[AllowMockObjectsWithoutExpectations]
class PackagesCalculatorTest extends TestCase
{
    /**
     * @var MultiQuoteValidatorInterface&MockObject
     */
    private MockObject $multiQuoteValidator;

    /**
     * @var PackageProcessor&MockObject
     */
    private MockObject $packageProcessor;

    /**
     * @var PackageManager&MockObject
     */
    private MockObject $packageManager;

    /**
     * @var PackageLimit&MockObject
     */
    private MockObject $packageLimit;

    /**
     * @var PackageMatching&MockObject
     */
    private MockObject $packageMatching;

    /**
     * @var RateRequestProviderInterface&MockObject
     */
    private MockObject $rateRequestProvider;

    /**
     * @var SerializerInterface&MockObject
     */
    private MockObject $serializer;

    /**
     * @var RateRequest
     */
    private RateRequest $rateRequest;

    /**
     * @var WeightConverterInterface&MockObject
     */
    private MockObject $weightConverter;

    /**
     * @var float
     */
    private float $toKgFactor = 1.0;

    /**
     * @var PackagesCalculator
     */
    private PackagesCalculator $subject;

    protected function setUp(): void
    {
        $this->multiQuoteValidator = $this->createMock(MultiQuoteValidatorInterface::class);
        $this->packageProcessor = $this->createMock(PackageProcessor::class);
        $this->packageManager = $this->createMock(PackageManager::class);
        $this->packageLimit = $this->createMock(PackageLimit::class);
        $this->packageMatching = $this->createMock(PackageMatching::class);
        $this->rateRequestProvider = $this->createMock(RateRequestProviderInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->rateRequest = new RateRequest();
        $this->weightConverter = $this->createMock(WeightConverterInterface::class);
        $this->weightConverter->method('convertToKg')->willReturnCallback(
            fn (float $weight): float => $weight * $this->toKgFactor
        );

        $this->rateRequestProvider->method('getRateRequest')->willReturn($this->rateRequest);
        $this->packageManager->method('process')->willReturnSelf();

        $this->subject = new PackagesCalculator(
            $this->multiQuoteValidator,
            $this->packageProcessor,
            $this->packageManager,
            $this->packageLimit,
            $this->packageMatching,
            $this->rateRequestProvider,
            $this->weightConverter
        );
    }

    /**
     * A single-package quote passes straight through untouched.
     *
     * @return void
     */
    public function testShouldReturnTheServiceListUnchangedWhenTheCartFitsOnePackage(): void
    {
        $this->packageLimit->method('isOverWeight')->willReturn(false);
        $this->packageManager->method('countPackages')->willReturn(1);
        $this->packageManager->method('getPackages')->willReturn(['full' => $this->createStub(Package::class)]);

        $pac = $this->service('03298', 25.90, 7);
        $sedex = $this->service('03220', 42.50, 3);
        $this->packageProcessor->method('process')->willReturn([$pac, $sedex]);

        $result = $this->subject->calculate();

        $this->assertSame([$pac, $sedex], $result);
    }

    /**
     * A cart split across two packages yields one row per method, priced as the sum of both packages.
     *
     * @return void
     */
    public function testShouldSumTheServicePricesAcrossPackagesWhenTheCartIsSplit(): void
    {
        $this->packageLimit->method('isOverWeight')->willReturn(false);
        $this->packageManager->method('countPackages')->willReturn(2);
        $this->packageManager->method('getPackages')->willReturn($this->twoPackages());

        $this->packageProcessor->method('process')->willReturnOnConsecutiveCalls(
            [$this->service('03298', 25.90, 7), $this->service('03220', 40.00, 3)],
            [$this->service('03298', 10.00, 5), $this->service('03220', 15.00, 2)]
        );

        $result = $this->subject->calculate();

        $this->assertContainsOnlyInstancesOf(Service::class, $result);
        $byCode = [];
        foreach ($result as $service) {
            $byCode[$service->getServiceCode()] = $service;
        }

        $this->assertEqualsWithDelta(35.90, $byCode['03298']->getShippingPrice(), 0.001);
        $this->assertSame(7, $byCode['03298']->getDeliveryTime());
        $this->assertEqualsWithDelta(55.00, $byCode['03220']->getShippingPrice(), 0.001);
        $this->assertSame(3, $byCode['03220']->getDeliveryTime());
    }

    /**
     * A method only one of the packages can ship is dropped: it cannot cover the whole order.
     *
     * @return void
     */
    public function testShouldDropAServiceThatIsMissingFromAnyPackageWhenTheCartIsSplit(): void
    {
        $this->packageLimit->method('isOverWeight')->willReturn(false);
        $this->packageManager->method('countPackages')->willReturn(2);
        $this->packageManager->method('getPackages')->willReturn($this->twoPackages());

        $this->packageProcessor->method('process')->willReturnOnConsecutiveCalls(
            [$this->service('03298', 25.90, 7), $this->service('03220', 40.00, 3)],
            [$this->service('03298', 10.00, 5)]
        );

        $result = $this->subject->calculate();

        $this->assertCount(1, $result);
        $this->assertSame('03298', $result[0]->getServiceCode());
    }

    /**
     * With Multi Quote, the matcher must receive the full-cart call and every package separately, not a merged list.
     *
     * @return void
     */
    public function testShouldHandTheMatcherTheFullCallAndEachPackageWhenMultiQuoteIsOn(): void
    {
        $this->givenAMultiQuoteSplit();
        $full = [$this->service('JTE_INT', 190.56, 4, 'J&T Express')];
        $first = [$this->service('03298', 120.00, 5)];
        $second = [$this->service('03298', 40.00, 6)];
        $this->packageProcessor->method('process')->willReturnOnConsecutiveCalls($full, $first, $second);

        $matched = [$this->service('MATCHED', 1.00, 1)];
        $this->packageMatching->expects($this->once())
            ->method('match')
            ->with(['full' => $full, 0 => $first, 1 => $second])
            ->willReturn($matched);

        $this->assertSame($matched, $this->subject->calculate());
    }

    /**
     * With Multi Quote, Correios is priced as the sum of the packages and other carriers come from the full-cart call.
     *
     * @return void
     */
    public function testShouldSumCorreiosPerPackageAndUseTheFullCallForOthersWhenMultiQuoteSplitsTheCart(): void
    {
        $serviceFactory = $this->createMock(ServiceFactory::class);
        $serviceFactory->method('create')->willReturnCallback(fn () => new Service($this->serializer));
        $subject = new PackagesCalculator(
            $this->multiQuoteValidator,
            $this->packageProcessor,
            $this->packageManager,
            $this->packageLimit,
            new PackageMatching($serviceFactory),
            $this->rateRequestProvider,
            $this->weightConverter
        );

        $this->givenAMultiQuoteSplit();
        $this->packageProcessor->method('process')->willReturnOnConsecutiveCalls(
            [$this->service('JTE_INT', 190.56, 4, 'J&T Express'), $this->service('03298', 999.00, 9)],
            [$this->service('03298', 120.00, 5), $this->service('JTE_INT', 50.00, 2, 'J&T Express')],
            [$this->service('03298', 40.00, 6)]
        );

        $byCode = [];
        foreach ($subject->calculate() as $service) {
            $byCode[$service->getServiceCode()] = $service;
        }

        $this->assertEqualsWithDelta(160.00, $byCode['03298']->getShippingPrice(), 0.001);
        $this->assertSame(6, (int) $byCode['03298']->getDeliveryTime());
        $this->assertEqualsWithDelta(190.56, $byCode['JTE_INT']->getShippingPrice(), 0.001);
        $this->assertCount(2, $byCode);
    }

    /**
     * The cart weight is compared with the kilogram limit in kilograms: 50 lb (22.7 kg) is quoted as one package.
     *
     * @return void
     */
    public function testShouldCompareTheCartWeightInKilogramsWhenTheStoreWeighsInPounds(): void
    {
        $this->toKgFactor = 0.453592;
        $this->rateRequest->setData('package_weight', 50);
        $this->packageLimit->expects($this->once())
            ->method('isOverWeight')
            ->with($this->callback(fn (float $weight): bool => abs($weight - 22.6796) < 0.001))
            ->willReturn(false);
        $this->multiQuoteValidator->expects($this->never())->method('canProcessMultiQuote');
        $this->packageManager->method('getPackages')->willReturn(['full' => $this->createStub(Package::class)]);
        $this->packageProcessor->method('process')->willReturn([$this->service('03298', 25.90, 7)]);

        $this->assertCount(1, $this->subject->calculate());
    }

    /**
     * Configures an overweight cart with Multi Quote on, split into the full-cart package plus two packages.
     *
     * @return void
     */
    private function givenAMultiQuoteSplit(): void
    {
        $this->packageLimit->method('isOverWeight')->willReturn(true);
        $this->multiQuoteValidator->method('canProcessMultiQuote')->willReturn(true);
        $this->packageManager->method('getPackages')->willReturn([
            'full' => $this->createStub(Package::class),
            0 => $this->createStub(Package::class),
            1 => $this->createStub(Package::class),
        ]);
    }

    /**
     * Returns two distinct package stubs, the shape PackageManager::getPackages() reports for a split cart.
     *
     * @return Package[]
     */
    private function twoPackages(): array
    {
        return [$this->createStub(Package::class), $this->createStub(Package::class)];
    }

    /**
     * Builds a real quote Service so the price arithmetic is exercised end to end.
     *
     * @param string $code
     * @param float  $price
     * @param int    $deliveryTime
     * @param string $carrier
     *
     * @return Service
     */
    private function service(string $code, float $price, int $deliveryTime, string $carrier = 'Correios'): Service
    {
        return new Service($this->serializer, [
            'Carrier'               => $carrier,
            'ServiceCode'           => $code,
            'ShippingPrice'         => (string) $price,
            'OriginalShippingPrice' => (string) $price,
            'DeliveryTime'          => (string) $deliveryTime,
            'Error'                 => false,
        ]);
    }
}
