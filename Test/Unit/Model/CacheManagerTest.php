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

namespace Frenet\Shipping\Test\Unit\Model;

use Frenet\Framework\Data\Serializer;
use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\Shipping\Model\Cache\Type\Frenet as FrenetCacheType;
use Frenet\Shipping\Model\CacheManager;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that CacheManager keeps each API answer under its request key and only while the Frenet cache type is on.
 */
#[AllowMockObjectsWithoutExpectations]
class CacheManagerTest extends TestCase
{
    /**
     * @var StateInterface&MockObject
     */
    private MockObject $cacheState;

    /**
     * @var CacheInterface&MockObject
     */
    private MockObject $cache;

    /**
     * @var CacheManager
     */
    private CacheManager $subject;

    protected function setUp(): void
    {
        $this->cacheState = $this->createMock(StateInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->subject = new CacheManager(new Json(), $this->cacheState, $this->cache);
    }

    /**
     * Confirms nothing is read or written while the Frenet cache type is disabled.
     *
     * @return void
     */
    public function testShouldBypassTheCacheWhenTheCacheTypeIsDisabled(): void
    {
        $this->cacheState->method('isEnabled')->with(FrenetCacheType::TYPE_IDENTIFIER)->willReturn(false);
        $this->cache->expects($this->never())->method('load');
        $this->cache->expects($this->never())->method('save');

        $this->assertNull($this->subject->load('request-key'));
        $this->assertFalse($this->subject->save('request-key', [$this->service('03298', 26.28)]));
    }

    /**
     * Confirms an answer is stored under the prefixed request key with the Frenet tag, as plain service data.
     *
     * @return void
     */
    public function testShouldStoreTheServiceDataWhenTheApiAnswered(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(true);
        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(fn (string $data) => json_decode($data, true)[0]['service_code'] === '03298'),
                'FRENET_QUOTE_request-key',
                [FrenetCacheType::CACHE_TAG]
            )
            ->willReturn(true);

        $this->assertTrue($this->subject->save('request-key', [$this->service('03298', 26.28)]));
    }

    /**
     * Confirms an empty answer, such as a timeout, is not cached so the next quote asks the API again.
     *
     * @return void
     */
    public function testShouldNotStoreAnythingWhenTheAnswerIsEmpty(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(true);
        $this->cache->expects($this->never())->method('save');

        $this->assertFalse($this->subject->save('request-key', []));
    }

    /**
     * Confirms a stored answer comes back as services carrying the same data.
     *
     * @return void
     */
    public function testShouldRebuildTheServicesWhenTheRequestWasCached(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(true);
        $this->cache->method('load')
            ->with('FRENET_QUOTE_request-key')
            ->willReturn((string) json_encode([$this->service('03220', 37.52)->getData()]));

        $services = $this->subject->load('request-key');

        $this->assertCount(1, $services);
        $this->assertSame('03220', $services[0]->getServiceCode());
        $this->assertEqualsWithDelta(37.52, $services[0]->getShippingPrice(), 0.001);
    }

    /**
     * Confirms a request that was never cached reports no answer.
     *
     * @return void
     */
    public function testShouldReportNoAnswerWhenTheRequestWasNotCached(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(true);
        $this->cache->method('load')->willReturn(false);

        $this->assertNull($this->subject->load('request-key'));
    }

    /**
     * Builds a Frenet service with the given code and price.
     *
     * @param string $code
     * @param float  $price
     *
     * @return Service
     */
    private function service(string $code, float $price): Service
    {
        return new Service(new Serializer(), [
            'ServiceCode' => $code,
            'Carrier' => 'Correios',
            'ShippingPrice' => (string) $price,
            'Error' => false,
        ]);
    }
}
