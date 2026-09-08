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

use Frenet\Shipping\Model\Carrier\Frenet;
use Frenet\Shipping\Model\ConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests the Frenet carrier gate methods (advertised code and the pre-flight checks that decide whether rates are collected).
 */
#[AllowMockObjectsWithoutExpectations]
class FrenetTest extends TestCase
{
    private ConfigInterface&MockObject $config;
    private StoreManagerInterface&MockObject $storeManager;
    private Frenet $subject;

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

    public function testShouldReportItsCarrierCode(): void
    {
        $this->assertSame(Frenet::CARRIER_CODE, $this->subject->getCarrierCode());
    }

    public function testShouldExposeTheCarrierCodeInTheAllowedMethods(): void
    {
        $this->config->method('getCarrierConfig')->with('name')->willReturn('frenet');

        $this->assertSame([Frenet::CARRIER_CODE => 'frenet'], $this->subject->getAllowedMethods());
    }

    public function testShouldNotCollectRatesWhenTheCarrierIsInactive(): void
    {
        $this->config->method('isActive')->willReturn(false);

        $this->assertFalse($this->subject->canCollectRates());
    }

    public function testShouldCollectRatesWhenActiveWithAnOriginPostcodeAndToken(): void
    {
        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getOriginPostcode')->willReturn('80010-030');
        $this->config->method('getToken')->willReturn('a-frenet-token');
        $this->storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        $this->assertTrue($this->subject->canCollectRates());
    }

    private function setDependency(string $property, object $value): void
    {
        $reflectionProperty = (new ReflectionClass(Frenet::class))->getProperty($property);
        $reflectionProperty->setValue($this->subject, $value);
    }
}
