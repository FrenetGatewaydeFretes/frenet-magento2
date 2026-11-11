<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Labels;

use Frenet\Shipping\Model\Labels\ResponseReader;
use PHPUnit\Framework\TestCase;

class ResponseReaderTest extends TestCase
{
    public function testReadsCamelAndPascalCase(): void
    {
        $reader = new ResponseReader();
        $this->assertSame(1, $reader->field(['shipmentId' => 1], 'shipmentId'));
        $this->assertSame(2, $reader->field(['ShipmentId' => 2], 'shipmentId'));
        $this->assertNull($reader->field([], 'shipmentId'));
        $this->assertSame('x', $reader->field(['x'], 0));
    }

    public function testRedactsTheTokenOfLabelUrls(): void
    {
        $reader = new ResponseReader();
        $this->assertSame(
            'https://frenet.test/label?p=***&x=1',
            $reader->redact('https://frenet.test/label?p=dG9rZW58MTIz&x=1')
        );
    }

    public function testExplainsKnownErrorsAndDropsStackTraces(): void
    {
        $reader = new ResponseReader();
        $this->assertStringContainsString('partner token', $reader->cleanError('Acesso negado para o parceiro'));
        $this->assertSame('Pedido inválido', $reader->cleanError("Pedido inválido --> at Frenet.Api.Labels"));
        $this->assertSame(
            'CEP inválido.',
            $reader->cleanError('CEP inválido. Detalhes identificador: 123e4567-e89b-12d3-a456-426614174000')
        );
    }
}
