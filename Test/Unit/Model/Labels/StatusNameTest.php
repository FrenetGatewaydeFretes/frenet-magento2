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
            $this->assertNotSame('', (new StatusName())->label($status));
            $this->assertContains((new StatusName())->tone($status), ['ok', 'wait', 'bad', 'off']);
        }
        $this->assertSame('ok', (new StatusName())->tone(4));
        $this->assertSame('bad', (new StatusName())->tone(3));
        $this->assertSame('off', (new StatusName())->tone(7));
        $this->assertSame('off', (new StatusName())->tone(99));
    }
}
