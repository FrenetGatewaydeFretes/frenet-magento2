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

namespace Frenet\Shipping\Test\Unit\Model\Quote;

use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Packages\PackageItemDistributor;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Model\Quote\MultiQuoteValidator;
use Frenet\Shipping\Model\WeightConverterInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplObjectStorage;

/**
 * Tests that MultiQuoteValidator weighs each packed item in kilograms against the 30 kg package limit.
 */
#[AllowMockObjectsWithoutExpectations]
class MultiQuoteValidatorTest extends TestCase
{
    /**
     * @var float
     */
    private const LBS_TO_KG = 0.453592;

    /**
     * Multi Quote may split the cart only when every item it packs fits one package, weighed in kilograms.
     *
     * @param float   $toKgFactor
     * @param float[] $itemWeights
     * @param bool    $expected
     *
     * @return void
     */
    #[DataProvider('weightProvider')]
    public function testShouldWeighEachPackedItemInKilogramsWhenCheckingMultiQuote(
        float $toKgFactor,
        array $itemWeights,
        bool $expected
    ): void {
        $this->assertSame($expected, $this->validator(true, $toKgFactor, $itemWeights)->canProcessMultiQuote());
    }

    /**
     * Provides the packing weights of the items in the store unit with the expected decision for a 30 kg limit.
     *
     * @return array<string, array{float, float[], bool}>
     */
    public static function weightProvider(): array
    {
        return [
            'kilograms, 20 kg items'                         => [1.0, [20.0, 20.0], true],
            'kilograms, a 30 kg item fits exactly'           => [1.0, [30.0, 5.0], true],
            'kilograms, a 35 kg item fits no package'        => [1.0, [35.0, 5.0], false],
            'pounds, a 50 lb (22.7 kg) item'                 => [self::LBS_TO_KG, [50.0, 20.0], true],
            'pounds, a 70 lb (31.8 kg) item fits no package' => [self::LBS_TO_KG, [70.0, 10.0], false],
        ];
    }

    /**
     * Multi Quote turned off never splits the cart.
     *
     * @return void
     */
    public function testShouldNotSplitWhenMultiQuoteIsDisabled(): void
    {
        $this->assertFalse($this->validator(false, 1.0, [10.0, 10.0])->canProcessMultiQuote());
    }

    /**
     * Builds the validator with a 30 kg limit over packed items weighing $itemWeights in the store unit.
     *
     * @param bool    $enabled
     * @param float   $toKgFactor
     * @param float[] $itemWeights
     *
     * @return MultiQuoteValidator
     */
    private function validator(bool $enabled, float $toKgFactor, array $itemWeights): MultiQuoteValidator
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('isMultiQuoteEnabled')->willReturn($enabled);
        $config->method('getPackageMaxWeight')->willReturn(30.0);

        $weights = new SplObjectStorage();
        $entries = [];
        foreach ($itemWeights as $weight) {
            $item = $this->createStub(AbstractItem::class);
            $weights[$item] = $weight;
            $entries[] = ['item' => $item, 'qty' => 1.0];
        }

        $distributor = $this->createStub(PackageItemDistributor::class);
        $distributor->method('distribute')->willReturn($entries);

        $current = null;
        $dimensionsExtractor = $this->createStub(DimensionsExtractorInterface::class);
        $dimensionsExtractor->method('setProductByCartItem')->willReturnCallback(
            function (AbstractItem $item) use (&$current, $dimensionsExtractor): DimensionsExtractorInterface {
                $current = $item;
                return $dimensionsExtractor;
            }
        );
        $dimensionsExtractor->method('getWeight')->willReturnCallback(
            function () use (&$current, $weights): float {
                return $weights[$current];
            }
        );

        $weightConverter = $this->createStub(WeightConverterInterface::class);
        $weightConverter->method('convertToKg')->willReturnCallback(
            fn (float $weight): float => $weight * $toKgFactor
        );

        return new MultiQuoteValidator(
            $config,
            new PackageLimit($config),
            $distributor,
            $dimensionsExtractor,
            $weightConverter
        );
    }
}
