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

use Frenet\Shipping\Model\Packages\PackageItemDistributor;
use Frenet\Shipping\Model\Quote\ItemQuantityCalculator;
use Frenet\Shipping\Model\Quote\QuoteItemValidatorInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PackageItemDistributor packs each item with its final quantity rounded half up.
 */
#[AllowMockObjectsWithoutExpectations]
class PackageItemDistributorTest extends TestCase
{
    /**
     * A decimal quantity is rounded once, at the end, half up: 0 to 4 rounds down and 5 to 9 rounds up.
     *
     * @param float $quantity
     * @param float $expected
     *
     * @return void
     */
    #[DataProvider('quantityProvider')]
    public function testShouldRoundTheFinalItemQuantityHalfUp(float $quantity, float $expected): void
    {
        $item = $this->createStub(AbstractItem::class);

        $validator = $this->createStub(QuoteItemValidatorInterface::class);
        $validator->method('validate')->willReturn(true);

        $quantityCalculator = $this->createStub(ItemQuantityCalculator::class);
        $quantityCalculator->method('calculate')->willReturn($quantity);

        $rateRequestProvider = $this->createStub(RateRequestProviderInterface::class);
        $rateRequestProvider->method('getRateRequest')->willReturn(new RateRequest(['all_items' => [$item]]));

        $distributed = (new PackageItemDistributor($validator, $quantityCalculator, $rateRequestProvider))
            ->distribute();

        $this->assertSame([['item' => $item, 'qty' => $expected]], $distributed);
    }

    /**
     * Provides final item quantities with the quantity expected in the packages.
     *
     * @return array<string, array{float, float}>
     */
    public static function quantityProvider(): array
    {
        return [
            'whole quantity'                       => [7.0, 7.0],
            '2.4 rounds down'                      => [2.4, 2.0],
            '2.5 rounds up'                        => [2.5, 3.0],
            '2.45 rounds down, not twice up'       => [2.45, 2.0],
            '2.9999 rounds up'                     => [2.9999, 3.0],
            '0.7 per bundle x 5 bundles (3.5) up'  => [0.7 * 5, 4.0],
        ];
    }
}
