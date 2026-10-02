<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Cep;

use Frenet\Shipping\Model\Cep\CepLookup;
use PHPUnit\Framework\TestCase;

class CepLookupTest extends TestCase
{
    /**
     * Only complete CEPs are looked up.
     *
     * @return void
     */
    public function testNormalize(): void
    {
        $this->assertSame('20040002', CepLookup::normalize('20040-002'));
        $this->assertSame('20040002', CepLookup::normalize(' 20.040-002 '));
        $this->assertSame('', CepLookup::normalize('2004000'));
        $this->assertSame('', CepLookup::normalize('200400021'));
    }
}
