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
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Packages\Package;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Model\Packages\PackageManager;
use Frenet\Shipping\Model\Packages\PackageMatching;
use Frenet\Shipping\Model\Packages\PackageProcessor;
use Frenet\Shipping\Model\Packages\PackagesCalculator;
use Frenet\Shipping\Model\Quote\MultiQuoteValidatorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests that PackagesCalculator decides on the packed cart weight and keeps Multi Quote results per package.
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
     * @var PackageLimit
     */
    private PackageLimit $packageLimit;

    /**
     * @var PackageMatching&MockObject
     */
    private MockObject $packageMatching;

    /**
     * @var SerializerInterface&MockObject
     */
    private MockObject $serializer;

    /**
     * @var int
     */
    private int $processCalls = 0;

    /**
     * @var Package[]
     */
    private array $fullStage = [];

    /**
     * @var Package[]
     */
    private array $splitStage = [];

    /**
     * @var PackagesCalculator
     */
    private PackagesCalculator $subject;

    protected function setUp(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getPackageMaxWeight')->willReturn(30.0);

        $this->multiQuoteValidator = $this->createMock(MultiQuoteValidatorInterface::class);
        $this->packageProcessor = $this->createMock(PackageProcessor::class);
        $this->packageManager = $this->createMock(PackageManager::class);
        $this->packageLimit = new PackageLimit($config);
        $this->packageMatching = $this->createMock(PackageMatching::class);
        $this->serializer = $this->createMock(SerializerInterface::class);

        $this->packageManager->method('process')->willReturnCallback(function (): PackageManager {
            $this->processCalls++;
            return $this->packageManager;
        });
        $this->packageManager->method('getPackages')->willReturnCallback(
            fn (): array => $this->processCalls > 1 ? $this->splitStage : $this->fullStage
        );

        $this->subject = $this->calculator($this->packageMatching);
    }

    /**
     * A packed cart within the limit goes in one call without asking Multi Quote; errored services are dropped.
     *
     * @return void
     */
    public function testShouldQuoteTheWholeCartInOneCallWhenItFitsOnePackage(): void
    {
        $this->givenAFullPackageOf(20.0);
        $pac = $this->service('03298', 25.90, 7);
        $sedex = $this->service('03220', 42.50, 3);
        $this->packageProcessor->expects($this->once())->method('process')
            ->willReturn([$pac, $this->service('04014', 0.0, 0, 'Correios', true), $sedex]);
        $this->multiQuoteValidator->expects($this->never())->method('canProcessMultiQuote');

        $this->assertSame([$pac, $sedex], $this->subject->calculate());
    }

    /**
     * A packed cart over the limit that Multi Quote cannot split (off, or an item over the limit) goes in one call.
     *
     * @return void
     */
    public function testShouldQuoteTheWholeCartInOneCallWhenMultiQuoteCannotSplitIt(): void
    {
        $this->givenAFullPackageOf(40.0);
        $this->multiQuoteValidator->method('canProcessMultiQuote')->willReturn(false);
        $jadlog = $this->service('JADLOG', 55.00, 6, 'Jadlog');
        $this->packageProcessor->expects($this->once())->method('process')->willReturn([$jadlog]);
        $this->packageMatching->expects($this->never())->method('match');

        $this->assertSame([$jadlog], $this->subject->calculate());
        $this->assertSame(1, $this->processCalls);
    }

    /**
     * An empty cart builds no package and is not sent to the API.
     *
     * @return void
     */
    public function testShouldReturnNoServicesWhenTheCartHasNothingToPack(): void
    {
        $this->packageProcessor->expects($this->never())->method('process');

        $this->assertSame([], $this->subject->calculate());
    }

    /**
     * With Multi Quote, the matcher must receive the full-cart call and every package separately, not a merged list.
     *
     * @return void
     */
    public function testShouldHandTheMatcherTheFullCallAndEachPackageWhenMultiQuoteSplitsTheCart(): void
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
        $subject = $this->calculator(new PackageMatching($serviceFactory));

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
     * The limit removed to pack the whole cart is back afterwards, so the next quote in the same request can split.
     *
     * @return void
     */
    public function testShouldRestoreTheWeightLimitAfterPackingTheWholeCart(): void
    {
        $this->givenAMultiQuoteSplit();
        $this->multiQuoteValidator = $this->createMock(MultiQuoteValidatorInterface::class);
        $this->multiQuoteValidator->method('canProcessMultiQuote')->willReturnOnConsecutiveCalls(false, true);
        $subject = $this->calculator($this->packageMatching);
        $this->packageProcessor->method('process')->willReturn([]);
        $this->packageMatching->expects($this->once())->method('match')->willReturn([]);

        $subject->calculate();
        $this->assertFalse($this->packageLimit->isUnlimited());

        $this->processCalls = 0;
        $subject->calculate();
    }

    /**
     * The limit is restored even when packing the whole cart fails.
     *
     * @return void
     */
    public function testShouldRestoreTheWeightLimitWhenPackingTheWholeCartFails(): void
    {
        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->method('process')->willThrowException(new RuntimeException('product not found'));
        $subject = new PackagesCalculator(
            $this->multiQuoteValidator,
            $this->packageProcessor,
            $packageManager,
            $this->packageLimit,
            $this->packageMatching
        );

        try {
            $subject->calculate();
            $this->fail('The packing failure should reach the caller.');
        } catch (RuntimeException $exception) {
            $this->assertSame('product not found', $exception->getMessage());
        }

        $this->assertSame(30.0, $this->packageLimit->getMaxWeight());
    }

    /**
     * Builds the calculator under test with the given matcher.
     *
     * @param PackageMatching $packageMatching
     *
     * @return PackagesCalculator
     */
    private function calculator(PackageMatching $packageMatching): PackagesCalculator
    {
        return new PackagesCalculator(
            $this->multiQuoteValidator,
            $this->packageProcessor,
            $this->packageManager,
            $this->packageLimit,
            $packageMatching
        );
    }

    /**
     * Makes the first packing pass build only the full-cart package, weighing $weight kg.
     *
     * @param float $weight
     *
     * @return void
     */
    private function givenAFullPackageOf(float $weight): void
    {
        $this->fullStage = ['full' => $this->package($weight)];
    }

    /**
     * Configures a 40 kg cart with Multi Quote on, split into the full-cart package plus two packages.
     *
     * @return void
     */
    private function givenAMultiQuoteSplit(): void
    {
        $this->givenAFullPackageOf(40.0);
        $this->multiQuoteValidator->method('canProcessMultiQuote')->willReturn(true);
        $this->splitStage = $this->fullStage + [0 => $this->package(30.0), 1 => $this->package(10.0)];
    }

    /**
     * Returns a package stub weighing $weight kg.
     *
     * @param float $weight
     *
     * @return Package
     */
    private function package(float $weight): Package
    {
        $package = $this->createStub(Package::class);
        $package->method('getTotalWeight')->willReturn($weight);

        return $package;
    }

    /**
     * Builds a real quote Service so the price arithmetic is exercised end to end.
     *
     * @param string $code
     * @param float  $price
     * @param int    $deliveryTime
     * @param string $carrier
     * @param bool   $error
     *
     * @return Service
     */
    private function service(
        string $code,
        float $price,
        int $deliveryTime,
        string $carrier = 'Correios',
        bool $error = false
    ): Service {
        return new Service($this->serializer, [
            'Carrier'               => $carrier,
            'ServiceCode'           => $code,
            'ShippingPrice'         => (string) $price,
            'OriginalShippingPrice' => (string) $price,
            'DeliveryTime'          => (string) $deliveryTime,
            'Error'                 => $error,
        ]);
    }
}
