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
 * Tests the Frenet carrier gate methods: the advertised code and the pre-flight checks that gate rate collection.
 */
#[AllowMockObjectsWithoutExpectations]
class FrenetTest extends TestCase
{
    /**
     * @var ConfigInterface&MockObject
     */
    private MockObject $config;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private MockObject $storeManager;

    /**
     * @var Frenet
     */
    private Frenet $subject;

    /**
     * Builds the carrier without its 24-dependency constructor, wiring only the collaborators the gate methods use.
     *
     * @return void
     */
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

    /**
     * Confirms the carrier advertises the code Magento uses to route rate requests to it.
     *
     * @return void
     */
    public function testShouldReportItsCarrierCode(): void
    {
        $this->assertSame(Frenet::CARRIER_CODE, $this->subject->getCarrierCode());
    }

    /**
     * Confirms the allowed-methods map keys the configured title by the carrier code, as core shipping expects.
     *
     * @return void
     */
    public function testShouldExposeTheCarrierCodeInTheAllowedMethods(): void
    {
        $this->config->method('getCarrierConfig')->with('name')->willReturn('frenet');

        $this->assertSame([Frenet::CARRIER_CODE => 'frenet'], $this->subject->getAllowedMethods());
    }

    /**
     * Confirms the inactive flag alone is enough to skip rate collection, before any other check runs.
     *
     * @return void
     */
    public function testShouldNotCollectRatesWhenTheCarrierIsInactive(): void
    {
        $this->config->method('isActive')->willReturn(false);

        $this->assertFalse($this->subject->canCollectRates());
    }

    /**
     * Confirms the carrier proceeds once active with both an origin postcode and a token configured.
     *
     * @return void
     */
    public function testShouldCollectRatesWhenActiveWithAnOriginPostcodeAndToken(): void
    {
        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getOriginPostcode')->willReturn('80010-030');
        $this->config->method('getToken')->willReturn('a-frenet-token');
        $this->storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        $this->assertTrue($this->subject->canCollectRates());
    }

    /**
     * Injects a collaborator straight into the carrier's private property, bypassing its skipped constructor.
     *
     * @param string $property
     * @param object $value
     *
     * @return void
     */
    private function setDependency(string $property, object $value): void
    {
        $reflectionProperty = (new ReflectionClass(Frenet::class))->getProperty($property);
        $reflectionProperty->setValue($this->subject, $value);
    }
}
