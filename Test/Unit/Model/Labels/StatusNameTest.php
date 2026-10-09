<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Labels;

use Frenet\Shipping\Model\Labels\StatusName;
use PHPUnit\Framework\TestCase;

class StatusNameTest extends TestCase
{
    public function testEveryFrenetStatusHasALabelAndTone(): void
    {
        foreach ([1, 2, 3, 4, 5, 6, 7, 9, 18] as $status) {
            $this->assertNotSame('', StatusName::label($status));
            $this->assertContains(StatusName::tone($status), ['ok', 'wait', 'bad', 'off']);
        }
        $this->assertSame('ok', StatusName::tone(4));
        $this->assertSame('bad', StatusName::tone(3));
        $this->assertSame('off', StatusName::tone(7));
        $this->assertSame('off', StatusName::tone(99));
    }
}
