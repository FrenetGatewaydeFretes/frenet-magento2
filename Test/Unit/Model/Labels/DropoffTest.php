<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Labels;

use Frenet\Shipping\Model\Labels\DropoffPoints;
use Frenet\Shipping\Model\Labels\FrenetOrderId;
use Frenet\Shipping\Model\Labels\ResponseReader;
use PHPUnit\Framework\TestCase;

class DropoffTest extends TestCase
{
    public function testGroupsEnabledServicesByCarrierSortedByName(): void
    {
        $out = (new DropoffPoints(new ResponseReader()))->group([
            [
                'serviceCode' => 'JAD1',
                'serviceDescription' => 'Jadlog .Package',
                'carrier' => 'Jadlog',
                'carrierCode' => 'JAD',
            ],
            [
                'serviceCode' => 'JTE1',
                'serviceDescription' => 'J&T Standard',
                'carrier' => 'J&T Express',
                'carrierCode' => 'JTE',
            ],
            [
                'serviceCode' => 'JAD2',
                'serviceDescription' => 'Jadlog Econômico',
                'carrier' => 'Jadlog',
                'carrierCode' => 'JAD',
            ],
            ['serviceCode' => 'LOG1', 'carrier' => 'Loggi', 'carrierCode' => 'LOG', 'enabled' => false],
            ['serviceCode' => 'X', 'carrier' => 'No code'],
            'not a service',
        ]);
        $this->assertSame(['JTE', 'JAD'], array_column($out, 'code'));
        $this->assertSame(['Jadlog .Package', 'Jadlog Econômico'], $out[1]['services']);
    }

    public function testBranchJoinsTheAddressAndRestoresTheLeadingZeroOfTheCep(): void
    {
        $b = (new DropoffPoints(new ResponseReader()))->branch([
            'id' => 'JAD-1', 'nickName' => ' Agência Jadlog Sé ', 'initials' => 'JAD011', 'type' => 'AGENCY',
            'address' => ['address' => 'Praça da Sé, 21', 'addressQuarter' => 'Sé', 'city' => 'São Paulo',
                'addressState' => 'SP', 'zipCode' => '1001000'],
        ]);
        $this->assertSame('Agência Jadlog Sé', $b['name']);
        $this->assertSame('Praça da Sé, 21 · Sé · São Paulo/SP · CEP 01001-000', $b['address']);
        $this->assertSame('', (new DropoffPoints(new ResponseReader()))->branch([])['id']);
        $this->assertSame('JTE', (new DropoffPoints(new ResponseReader()))->code(' jte;'));
    }

    public function testASecondLabelOfTheSameOrderGetsItsOwnFrenetOrderId(): void
    {
        $this->assertSame('000000123', (new FrenetOrderId())->build('000000123', 1));
        $this->assertSame('000000123-2', (new FrenetOrderId())->build('000000123', 2));
        $this->assertSame('000000123', (new FrenetOrderId())->incrementId('000000123-2'));
        $this->assertSame('000000123', (new FrenetOrderId())->incrementId('000000123'));
    }
}
