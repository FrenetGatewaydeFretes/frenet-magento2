<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\TrackingSync;

use Frenet\Shipping\Model\TrackingSync\StatusMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Unit tests of the Frenet tracking event to order status mapping.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class StatusMapperTest extends TestCase
{
    /**
     * Event description (and Frenet EventType) → order status.
     *
     * @return string[][]
     */
    public static function events(): array
    {
        return [
            'delivered text' => ['Objeto entregue ao destinatário', '', StatusMapper::DELIVERED],
            'delivered type' => ['Finalizado', '9', StatusMapper::DELIVERED],
            'not delivered' => ['Objeto não entregue - carteiro não atendido', '', StatusMapper::IN_TRANSIT],
            'drop-off point' => ['Objeto entregue no ponto de postagem', '', StatusMapper::IN_TRANSIT],
            'pickup' => ['Objeto aguardando retirada no endereço indicado', '', StatusMapper::PICKUP],
            'returning text' => ['Objeto em devolução ao remetente', '', StatusMapper::RETURNING],
            'returned type' => ['Objeto devolvido', '3', StatusMapper::RETURNING],
            'in transit' => ['Objeto em trânsito - por favor aguarde', '1', StatusMapper::IN_TRANSIT],
            'label issued' => ['Etiqueta emitida - Aguardando postagem pelo remetente', '1', ''],
            'label expired' => ['Etiqueta expirada - Prazo para postagem encerrado', '1', ''],
        ];
    }

    /**
     * @param string $description
     * @param string $type
     * @param string $expected
     * @return void
     */
    #[DataProvider('events')]
    public function testStatusFor(string $description, string $type, string $expected): void
    {
        $this->assertSame($expected, (new StatusMapper())->statusFor($description, $type));
    }

    /**
     * @return void
     */
    public function testServiceCodeAndEventTime(): void
    {
        $this->assertSame('03298', (new StatusMapper())->serviceCode('frenetshipping_03298'));
        $this->assertSame('', (new StatusMapper())->serviceCode('flatrate_flatrate'));
        $this->assertGreaterThan(
            (new StatusMapper())->eventTime('01/10/2026 10:00'),
            (new StatusMapper())->eventTime('02/10/2026 09:00')
        );
        $this->assertSame(0, (new StatusMapper())->eventTime('ontem'));
    }

    public function testDiXmlRulesEqualTheDefaults(): void
    {
        $xml = new SimpleXMLElement((string) file_get_contents(dirname(__DIR__, 4) . '/etc/di.xml'));
        $xml->registerXPathNamespace('x', 'http://www.w3.org/2001/XMLSchema-instance');
        $items = $xml->xpath(
            '//type[@name="Frenet\\Shipping\\Model\\TrackingSync\\StatusMapper"]/arguments/argument[@name="rules"]/item'
        );
        $rules = [];
        foreach ($items ?: [] as $item) {
            $rule = [];
            foreach ($item->item as $field) {
                $type = (string) $field->attributes('http://www.w3.org/2001/XMLSchema-instance')['type'];
                $rule[(string) $field['name']] = $type === 'const' ? constant((string) $field) : (string) $field;
            }
            $rules[(string) $item['name']] = $rule;
        }
        $this->assertSame(StatusMapper::DEFAULT_RULES, $rules);
        $this->assertSame(
            StatusMapper::DELIVERED,
            (new StatusMapper($rules))->statusFor('Objeto entregue ao destinatário')
        );
    }
}
