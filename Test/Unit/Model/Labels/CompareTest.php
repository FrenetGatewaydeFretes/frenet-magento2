<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Labels;

use Frenet\Shipping\Model\Labels\Compare;
use PHPUnit\Framework\TestCase;

class CompareTest extends TestCase
{
    /**
     * Services of one order, as Client::quoteAll() returns them.
     *
     * @return array
     */
    private function services(): array
    {
        $s = static fn (string $code, string $carrier, float $price, int $days) => [
            'code' => $code, 'carrier' => $carrier, 'carrier_code' => strtoupper(substr($carrier, 0, 3)),
            'name' => $code, 'price' => $price, 'days' => $days,
        ];
        return [
            $s('JTE_INT', 'J&T Express', 14.29, 3),
            $s('03298', 'Correios', 34.39, 5),
            $s('03220', 'Correios', 56.82, 1),
            $s('TOT', 'Total Express', 21.22, 2),
        ];
    }

    public function testBestPriceAndBestTime(): void
    {
        $this->assertSame(0, Compare::bestPrice($this->services()));
        $this->assertSame(2, Compare::bestTime($this->services()));
        $this->assertNull(Compare::bestPrice([]));
    }

    public function testPreselectKeepsTheCustomerChoiceUnlessAutoBest(): void
    {
        $services = $this->services();
        $this->assertSame(1, Compare::preselect($services, '03298', 'price', false));
        $this->assertSame(0, Compare::preselect($services, '03298', 'price', true));
        $this->assertSame(2, Compare::preselect($services, '03298', 'time', true));
        // The customer's service is no longer offered: fall back to the best one.
        $this->assertSame(0, Compare::preselect($services, 'GONE', 'price', false));
    }

    public function testCarriersHeaderTotals(): void
    {
        $order = $this->services();
        $header = Compare::carriers([$order, $order, array_slice($order, 0, 1)]);
        $byName = array_column($header['carriers'], null, 'carrier');
        $this->assertSame(3, $header['orders']);
        $this->assertSame(42.87, $header['best_total']);
        $this->assertSame(3, $byName['J&T Express']['available']);
        $this->assertTrue($byName['J&T Express']['all']);
        $this->assertSame(2, $byName['Correios']['available']);
        $this->assertFalse($byName['Correios']['all']);
        $this->assertSame(68.78, $byName['Correios']['best_price']);
        $this->assertSame(113.64, $byName['Correios']['best_time']);
    }

    public function testSummarize(): void
    {
        $s = $this->services();
        $sum = Compare::summarize([$s[0], $s[1], $s[0]]);
        $this->assertSame(3, $sum['count']);
        $this->assertSame(62.97, $sum['total']);
        $this->assertSame(['count' => 2, 'total' => 28.58], $sum['carriers']['J&T Express']);
    }
}
