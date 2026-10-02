<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Labels;

use Frenet\Shipping\Model\Labels\AddressMapper;
use PHPUnit\Framework\TestCase;

class AddressMapperTest extends TestCase
{
    private function map(array $street): array
    {
        $a = AddressMapper::toFrenet($street, 'Rio de Janeiro', 'rj', '20040-002');
        return [$a['Street'], $a['AddressNumber'], $a['AddressComplement'], $a['AddressQuarter']];
    }

    public function testFourAndThreeLines(): void
    {
        $this->assertSame(['Rua A', '10', 'Apto 2', 'Centro'], $this->map(['Rua A', '10', 'Apto 2', 'Centro']));
        $this->assertSame(['Rua A', '10', '', 'Centro'], $this->map(['Rua A', '10', 'Centro']));
    }

    public function testTwoLinesWithBareNumberInSecondLine(): void
    {
        $this->assertSame(['Av Rio Branco', '1', '', '-'], $this->map(['Av Rio Branco', '1']));
        $this->assertSame(['Av Rio Branco', 'S/N', '', '-'], $this->map(['Av Rio Branco', 'S/N']));
        $this->assertSame(['Av Rio Branco', '12', '', '-'], $this->map(['Av Rio Branco', 'nº 12']));
    }

    public function testNumberSplitOffTheStreet(): void
    {
        $this->assertSame(['Rua A', '123', '', 'Centro'], $this->map(['Rua A, 123', 'Centro']));
        $this->assertSame(['Rua A', '45B', '', '-'], $this->map(['Rua A nº 45B']));
        $this->assertSame(['Rua 25 de Março', '', '', 'Centro'], $this->map(['Rua 25 de Março', 'Centro']));
    }

    public function testStateAndZip(): void
    {
        $a = AddressMapper::toFrenet(['Rua A', '1', 'Centro'], 'Rio', 'rj', '20040-002');
        $this->assertSame('RJ', $a['AddressState']);
        $this->assertSame('20040002', $a['ZipCode']);
    }
}
