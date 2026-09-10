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
use Frenet\Shipping\Model\Packages\PackageMatching;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PackageMatching binds Correios per package while keeping the other carriers from the full call, once each.
 */
#[AllowMockObjectsWithoutExpectations]
class PackageMatchingTest extends TestCase
{
    /**
     * @var ServiceFactory&MockObject
     */
    private MockObject $serviceFactory;

    /**
     * @var SerializerInterface&MockObject
     */
    private MockObject $serializer;

    /**
     * @var PackageMatching
     */
    private PackageMatching $subject;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->serviceFactory = $this->createMock(ServiceFactory::class);
        $this->serviceFactory->method('create')->willReturnCallback(
            fn (): Service => new Service($this->serializer)
        );

        $this->subject = new PackageMatching($this->serviceFactory);
    }

    /**
     * The full-call Correios rows are dropped so a bound Correios service is never listed twice.
     *
     * @return void
     */
    public function testShouldNotDuplicateCorreiosServicesFromTheFullCall(): void
    {
        $result = $this->subject->match([
            'full' => [$this->service('03298', 'Correios', 25.90, 7), $this->service('EXP', 'Transp Teste', 19.75, 5)],
            0 => [$this->service('03298', 'Correios', 25.90, 7)],
            1 => [$this->service('03298', 'Correios', 12.00, 4)],
        ]);

        $byCode = [];
        foreach ($result as $service) {
            $byCode[$service->getServiceCode()][] = $service;
        }

        $this->assertArrayHasKey('03298', $byCode);
        $this->assertArrayHasKey('EXP', $byCode);
        $this->assertCount(1, $byCode['03298'], 'Correios must appear once, bound across packages');
        $this->assertCount(1, $byCode['EXP'], 'non-Correios must appear once, from the full call');
    }

    /**
     * A Correios service is priced as the sum of every package and keeps the slowest delivery time.
     *
     * @return void
     */
    public function testShouldSumTheCorreiosPriceAcrossPackages(): void
    {
        $result = $this->subject->match([
            'full' => [$this->service('03298', 'Correios', 25.90, 7)],
            0 => [$this->service('03298', 'Correios', 25.90, 7)],
            1 => [$this->service('03298', 'Correios', 12.00, 4)],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('03298', $result[0]->getServiceCode());
        $this->assertEqualsWithDelta(37.90, $result[0]->getShippingPrice(), 0.001);
    }

    /**
     * The non-Correios services from the full call survive even when no package bound a Correios quote.
     *
     * @return void
     */
    public function testShouldKeepTheFullCallCarriersWhenNoPackageHasCorreios(): void
    {
        $result = $this->subject->match([
            'full' => [$this->service('EXP', 'Transp Teste', 19.75, 5)],
            0 => [$this->service('EXP', 'Transp Teste', 19.75, 5)],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('EXP', $result[0]->getServiceCode());
    }

    /**
     * Builds a real quote Service so the matcher's price and carrier reads run for real.
     *
     * @param string $code
     * @param string $carrier
     * @param float  $price
     * @param int    $deliveryTime
     *
     * @return Service
     */
    private function service(string $code, string $carrier, float $price, int $deliveryTime): Service
    {
        return new Service($this->serializer, [
            'ServiceCode'           => $code,
            'Carrier'               => $carrier,
            'ShippingPrice'         => (string) $price,
            'OriginalShippingPrice' => (string) $price,
            'DeliveryTime'          => (string) $deliveryTime,
            'OriginalDeliveryTime'  => (string) $deliveryTime,
            'Error'                 => false,
        ]);
    }
}
