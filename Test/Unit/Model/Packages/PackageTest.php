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
            'kilograms, 10 kg units'          => [10.0, 1.0, 7.0, [3.0, 3.0, 1.0]],
            'pounds, 20 lb (9.07 kg) units'   => [20.0, self::LBS_TO_KG, 7.0, [3.0, 3.0, 1.0]],
            'pounds, 40 lb (18.14 kg) units'  => [40.0, self::LBS_TO_KG, 3.0, [1.0, 1.0, 1.0]],
        ];
    }

    /**
     * Builds an empty Package with a 30 kg limit whose items weigh $unitWeight in the store unit.
     *
     * @param float $unitWeight
     * @param float $toKgFactor
     *
     * @return Package
     */
    private function package(float $unitWeight, float $toKgFactor): Package
    {
        $dimensionsExtractor = $this->createStub(DimensionsExtractorInterface::class);
        $dimensionsExtractor->method('getWeight')->willReturn($unitWeight);

        $config = $this->createStub(ConfigInterface::class);
        $config->method('getPackageMaxWeight')->willReturn(30.0);

        $weightConverter = $this->createStub(WeightConverterInterface::class);
        $weightConverter->method('convertToKg')->willReturnCallback(
            fn (float $weight): float => $weight * $toKgFactor
        );

        return new Package(
            $dimensionsExtractor,
            $this->createStub(PackageItemFactory::class),
            new PackageLimit($config),
            $weightConverter
        );
    }
}
