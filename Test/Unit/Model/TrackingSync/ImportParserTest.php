<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\TrackingSync;

use Frenet\Shipping\Model\TrackingSync\ImportParser;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests of the bulk tracking import parser.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class ImportParserTest extends TestCase
{
    /**
     * Separators, header row, duplicates and unreadable lines.
     *
     * @return void
     */
    public function testParse(): void
    {
        $result = ImportParser::parse(
            "pedido;código\n000000012;aa123456789br\n#000000013,AA987654321BR\n\n"
            . "000000014\tAB111222333BR\n000000012;AA123456789BR\numa-coluna\n"
        );
        $this->assertSame([
            ['order' => '000000012', 'code' => 'AA123456789BR'],
            ['order' => '000000013', 'code' => 'AA987654321BR'],
            ['order' => '000000014', 'code' => 'AB111222333BR'],
        ], $result['rows']);
        $this->assertSame([7], $result['invalid']);
    }

    /**
     * A header without accents ("pedido;codigo") is not a row.
     *
     * @return void
     */
    public function testHeaderWithoutAccents(): void
    {
        foreach (["pedido;codigo", "#pedido;CODIGO", "pedido;código", "Pedido,Codigo"] as $header) {
            $result = ImportParser::parse($header . "\n000000048;AA123456785BR");
            $this->assertSame([['order' => '000000048', 'code' => 'AA123456785BR']], $result['rows'], $header);
            $this->assertSame([], $result['invalid'], $header);
        }
        $result = ImportParser::parse("pedido;codigo\n000000048;AA123456785BR");
        $this->assertSame([['order' => '000000048', 'code' => 'AA123456785BR']], $result['rows']);
        $this->assertSame([], $result['invalid']);
    }
}
