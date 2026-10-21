<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Cep;

use Frenet\Shipping\Model\Cep\CepLookup;
use Frenet\Shipping\Model\Extras\Settings;
use Frenet\Shipping\Model\Labels\Client;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CepLookupTest extends TestCase
{
    /** The lookup with every collaborator mocked: normalize() needs none of them. */
    private function lookup(): CepLookup
    {
        return new CepLookup(
            $this->createStub(Client::class),
            $this->createStub(CacheInterface::class),
            $this->createStub(Json::class),
            $this->createStub(RegionFactory::class),
            $this->createStub(Settings::class)
        );
    }

    /**
     * Only complete CEPs are looked up.
     *
     * @return void
     */
    public function testNormalize(): void
    {
        $this->assertSame('20040002', $this->lookup()->normalize('20040-002'));
        $this->assertSame('20040002', $this->lookup()->normalize(' 20.040-002 '));
        $this->assertSame('', $this->lookup()->normalize('2004000'));
        $this->assertSame('', $this->lookup()->normalize('200400021'));
    }
}
