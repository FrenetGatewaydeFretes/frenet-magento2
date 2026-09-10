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
use Frenet\Shipping\Model\Packages\Package;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Model\Packages\PackageManager;
use Frenet\Shipping\Model\Packages\PackageMatching;
use Frenet\Shipping\Model\Packages\PackageProcessor;
use Frenet\Shipping\Model\Packages\PackagesCalculator;
use Frenet\Shipping\Model\Quote\MultiQuoteValidatorInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PackagesCalculator always returns a flat, consolidated Service list even when the cart is split.
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

        $this->rateRequestProvider->method('getRateRequest')->willReturn($this->createMock(RateRequest::class));
        $this->packageManager->method('process')->willReturnSelf();

        $this->subject = new PackagesCalculator(
            $this->multiQuoteValidator,
            $this->packageProcessor,
            $this->packageManager,
            $this->packageLimit,
            $this->packageMatching,
            $this->rateRequestProvider
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
     *
     * @return Service
     */
    private function service(string $code, float $price, int $deliveryTime): Service
    {
        return new Service($this->serializer, [
            'ServiceCode'           => $code,
            'ShippingPrice'         => (string) $price,
            'OriginalShippingPrice' => (string) $price,
            'DeliveryTime'          => (string) $deliveryTime,
            'Error'                 => false,
        ]);
    }
}
