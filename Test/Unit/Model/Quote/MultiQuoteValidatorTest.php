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

use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Model\Quote\MultiQuoteValidator;
use Frenet\Shipping\Model\WeightConverterInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests that MultiQuoteValidator compares the cart and item weights with the 30 kg package limit in kilograms.
 */
#[AllowMockObjectsWithoutExpectations]
class MultiQuoteValidatorTest extends TestCase
{
    /**
     * @var float
     */
    private const LBS_TO_KG = 0.453592;

    /**
     * Multi Quote splits only a cart over the limit whose items each fit one package, both weighed in kilograms.
     *
     * @param float   $toKgFactor
     * @param float   $cartWeight
     * @param float[] $itemWeights
     * @param bool    $expected
     *
     * @return void
     */
    #[DataProvider('weightProvider')]
    public function testShouldWeighTheCartAndItsItemsInKilogramsWhenCheckingMultiQuote(
        float $toKgFactor,
        float $cartWeight,
        array $itemWeights,
        bool $expected
    ): void {
        $items = array_map(fn (float $weight): DataObject => new DataObject(['weight' => $weight]), $itemWeights);
        $rateRequest = new RateRequest(['package_weight' => $cartWeight, 'all_items' => $items]);

        $this->assertSame($expected, $this->validator($toKgFactor, $rateRequest)->canProcessMultiQuote());
    }

    /**
     * Provides store weights with the expected Multi Quote decision for a 30 kg limit.
     *
     * @return array<string, array{float, float, float[], bool}>
     */
    public static function weightProvider(): array
    {
        return [
            'kilograms, 40 kg cart of 20 kg items'              => [1.0, 40.0, [20.0, 20.0], true],
            'kilograms, 25 kg cart fits one package'            => [1.0, 25.0, [12.5, 12.5], false],
            'kilograms, 35 kg item does not fit any package'    => [1.0, 40.0, [35.0, 5.0], false],
            'pounds, 50 lb (22.7 kg) cart fits one package'     => [self::LBS_TO_KG, 50.0, [25.0, 25.0], false],
            'pounds, 70 lb (31.8 kg) cart of a 50 lb (22.7 kg) item' => [self::LBS_TO_KG, 70.0, [50.0, 20.0], true],
            'pounds, 70 lb (31.8 kg) item does not fit any package'  => [self::LBS_TO_KG, 80.0, [70.0, 10.0], false],
        ];
    }

    /**
     * Builds the validator with Multi Quote on, a 30 kg limit and the store unit converted by $toKgFactor.
     *
     * @param float       $toKgFactor
     * @param RateRequest $rateRequest
     *
     * @return MultiQuoteValidator
     */
    private function validator(float $toKgFactor, RateRequest $rateRequest): MultiQuoteValidator
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('isMultiQuoteEnabled')->willReturn(true);
        $config->method('getPackageMaxWeight')->willReturn(30.0);

        $rateRequestProvider = $this->createStub(RateRequestProviderInterface::class);
        $rateRequestProvider->method('getRateRequest')->willReturn($rateRequest);

        $weightConverter = $this->createStub(WeightConverterInterface::class);
        $weightConverter->method('convertToKg')->willReturnCallback(
            fn (float $weight): float => $weight * $toKgFactor
        );

        return new MultiQuoteValidator($config, new PackageLimit($config), $rateRequestProvider, $weightConverter);
    }
}
