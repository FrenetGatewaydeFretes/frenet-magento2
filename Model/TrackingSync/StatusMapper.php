<?php
/**
 * Frenet Shipping Gateway — Frenet tracking event → order status, decided by an ordered rule table (unit tested).
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\TrackingSync;

class StatusMapper implements StatusMapperInterface
{
    /**
     * Default rules, first match wins. "event" matches Frenet's EventType; "pattern" is a regex on the lower-cased
     * description; "status" '' means "not posted yet, no change". Nothing matching is "in transit".
     * Correios events before posting: "Etiqueta emitida - Aguardando postagem", "Etiqueta expirada".
     */
    public const DEFAULT_RULES = [
        'event_delivered' => ['event' => '9', 'status' => self::DELIVERED],
        'event_returned' => ['event' => '3', 'status' => self::RETURNING],
        'not_posted' => [
            'pattern' => '/etiqueta emitida|etiqueta expirada|aguardando postagem|prazo para postagem/u',
            'status' => '',
        ],
        'returning' => ['pattern' => '/devolvido|devolução|devolucao/u', 'status' => self::RETURNING],
        'delivered' => [
            'pattern' => '/^(?!.*(n[aã]o entregue|ponto de postagem)).*entregue/u',
            'status' => self::DELIVERED,
        ],
        'pickup' => ['pattern' => '/aguardando retirada|dispon[ií]vel para retirada/u', 'status' => self::PICKUP],
    ];

    /** @var mixed[] */
    private readonly array $rules;

    /**
     * @param mixed[] $rules Ordered rules (see di.xml); the defaults when empty.
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules ?: self::DEFAULT_RULES;
    }

    /**
     * @inheritDoc
     */
    public function statusFor(string $description, string $eventType = ''): string
    {
        $text = mb_strtolower($description);
        foreach ($this->rules as $rule) {
            if ($this->matches($rule, $text, $eventType)) {
                return (string) $rule['status'];
            }
        }
        return self::IN_TRANSIT;
    }

    /**
     * Whether one rule applies to the event.
     *
     * @param mixed[] $rule
     * @param string $text Lower-cased description.
     * @param string $eventType
     * @return bool
     */
    private function matches(array $rule, string $text, string $eventType): bool
    {
        if (isset($rule['event'])) {
            return $eventType !== '' && (string) $rule['event'] === $eventType;
        }
        return isset($rule['pattern']) && preg_match((string) $rule['pattern'], $text) === 1;
    }

    /**
     * Timestamp of a Frenet event date ("dd/mm/YYYY HH:ii"); 0 when unreadable.
     *
     * @param string $date
     * @return int
     */
    public function eventTime(string $date): int
    {
        $parsed = date_create_from_format('d/m/Y H:i', trim($date));
        return $parsed ? $parsed->getTimestamp() : 0;
    }

    /**
     * Frenet service code of an order shipped with the Frenet carrier ("frenetshipping_03298" → "03298").
     *
     * @param string $shippingMethod
     * @return string
     */
    public function serviceCode(string $shippingMethod): string
    {
        return str_starts_with($shippingMethod, 'frenetshipping_') ? substr($shippingMethod, 15) : '';
    }
}
