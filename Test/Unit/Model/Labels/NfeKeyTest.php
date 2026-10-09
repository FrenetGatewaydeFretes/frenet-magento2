<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Labels;

use Frenet\Shipping\Model\Labels\NfeKey;
use PHPUnit\Framework\TestCase;

class NfeKeyTest extends TestCase
{
    private const KEY = '35260911222333000181550010000123451000000014';

    public function testValidKeyWithSpacesAndDots(): void
    {
        $this->assertTrue(NfeKey::isValid(self::KEY));
        $this->assertTrue(NfeKey::isValid(trim(chunk_split(self::KEY, 4, ' '))));
        $this->assertTrue(NfeKey::isValid(str_replace('0001', '0001.', self::KEY)));
    }

    public function testRejectsWrongCheckDigitAndLength(): void
    {
        $this->assertFalse(NfeKey::isValid(substr(self::KEY, 0, 43) . '5'));
        $this->assertFalse(NfeKey::isValid(substr(self::KEY, 0, 40)));
        $this->assertFalse(NfeKey::isValid(''));
    }

    public function testParseReadsNumberSeriesAndCnpj(): void
    {
        $nf = NfeKey::parse(self::KEY);
        $this->assertSame('12345', $nf['number']);
        $this->assertSame('1', $nf['series']);
        $this->assertSame('11222333000181', $nf['cnpj']);
        $this->assertSame('55', $nf['model']);
    }
}
