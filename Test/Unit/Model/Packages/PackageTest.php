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

use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Packages\Package;
use Frenet\Shipping\Model\Packages\PackageItem;
use Frenet\Shipping\Model\Packages\PackageItemFactory;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Model\WeightConverterInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Package plans how many units of an item fit per package by their weight in kilograms.
 */
#[AllowMockObjectsWithoutExpectations]
class PackageTest extends TestCase
{
    /**
     * @var float
     */
    private const LBS_TO_KG = 0.453592;

    /**
     * Units are counted by their kilogram weight against the kilogram limit, whatever the store weight unit.
     *
     * @param float   $unitWeight
     * @param float   $toKgFactor
     * @param float   $requestedQty
     * @param float[] $expectedQuantities
     *
     * @return void
     */
    #[DataProvider('weightUnitProvider')]
    public function testShouldFitUnitsByTheirKilogramWeightWhenPlanningPackages(
        float $unitWeight,
        float $toKgFactor,
        float $requestedQty,
        array $expectedQuantities
    ): void {
        $plan = $this->package($unitWeight, $toKgFactor)
            ->planQuantitiesFor($this->createStub(AbstractItem::class), $requestedQty);

        $this->assertSame($expectedQuantities, array_column($plan, 'qty'));
        $this->assertSame(
            array_map(fn (int $index): bool => $index > 0, array_keys($expectedQuantities)),
            array_column($plan, 'newPackage')
        );
    }

    /**
     * Provides unit weights in the store unit with the expected quantity per package for a 30 kg limit.
     *
     * @return array<string, array{float, float, float, float[]}>
     */
    public static function weightUnitProvider(): array
    {
        return [
            'kilograms, 10 kg units'               => [10.0, 1.0, 7.0, [3.0, 3.0, 1.0]],
            'pounds, 20 lb (9.07 kg) units'        => [20.0, self::LBS_TO_KG, 7.0, [3.0, 3.0, 1.0]],
            'pounds, 40 lb (18.14 kg) units'       => [40.0, self::LBS_TO_KG, 3.0, [1.0, 1.0, 1.0]],
            'pounds, 0.5049 lb (0.22902 kg) units' => [0.5049, self::LBS_TO_KG, 200.0, [130.0, 70.0]],
        ];
    }

    /**
     * The first batch planned for a package always passes the weight limit check that adding it runs.
     *
     * @param float $unitWeight
     * @param float $toKgFactor
     * @param float $packedQty
     * @param float $packedUnitWeight
     * @param float $expectedFirstBatch
     *
     * @return void
     */
    #[DataProvider('limitEdgeProvider')]
    public function testShouldPlanOnlyBatchesThatPassTheWeightLimitCheck(
        float $unitWeight,
        float $toKgFactor,
        float $packedQty,
        float $packedUnitWeight,
        float $expectedFirstBatch
    ): void {
        $package = $this->package($unitWeight, $toKgFactor, $packedUnitWeight * $toKgFactor * $packedQty);

        if ($packedQty > 0) {
            $this->assertTrue(
                $package->addItem($this->createStub(AbstractItem::class), $packedQty, $packedUnitWeight)
            );
        }

        $item = $this->createStub(AbstractItem::class);
        $plan = $package->planQuantitiesFor($item, 200.0);

        $this->assertSame($expectedFirstBatch, $plan[0]['qty']);
        $this->assertTrue($package->canAddItem($item, $plan[0]['qty'], $plan[0]['unitWeight']));
    }

    /**
     * Provides unit weights whose packing lands on the 30 kg limit once weighed in floating point kilograms.
     *
     * @return array<string, array{float, float, float, float, float}>
     */
    public static function limitEdgeProvider(): array
    {
        return [
            'kilograms, 2.7 kg units after 3 x 3.7 kg' => [2.7, 1.0, 3.0, 3.7, 7.0],
            'pounds, 0.5049 lb units, empty package'   => [0.5049, self::LBS_TO_KG, 0.0, 0.0, 130.0],
        ];
    }

    /**
     * Builds an empty 30 kg Package: new items weigh $unitWeight in the store unit, added items $packedWeight kg.
     *
     * @param float $unitWeight
     * @param float $toKgFactor
     * @param float $packedWeight
     *
     * @return Package
     */
    private function package(float $unitWeight, float $toKgFactor, float $packedWeight = 0.0): Package
    {
        $dimensionsExtractor = $this->createStub(DimensionsExtractorInterface::class);
        $dimensionsExtractor->method('getWeight')->willReturn($unitWeight);

        $config = $this->createStub(ConfigInterface::class);
        $config->method('getPackageMaxWeight')->willReturn(30.0);

        $weightConverter = $this->createStub(WeightConverterInterface::class);
        $weightConverter->method('convertToKg')->willReturnCallback(
            fn (float $weight): float => $weight * $toKgFactor
        );

        $packageItem = $this->createStub(PackageItem::class);
        $packageItem->method('getTotalWeight')->willReturn($packedWeight);

        $packageItemFactory = $this->createStub(PackageItemFactory::class);
        $packageItemFactory->method('create')->willReturn($packageItem);

        return new Package(
            $dimensionsExtractor,
            $packageItemFactory,
            new PackageLimit($config),
            $weightConverter
        );
    }
}
