<?php
/**
 * Frenet Shipping Gateway — Frenet tracking event → order status. Pure functions (unit tested).
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\TrackingSync;

class StatusMapper
{
    public const IN_TRANSIT = 'frenet_in_transit';
    public const PICKUP = 'frenet_pickup';
    public const DELIVERED = 'frenet_delivered';
    public const RETURNING = 'frenet_returning';

    /** Status labels as shown to the store and the customer (Brazilian stores). */
    public const LABELS = [
        self::IN_TRANSIT => 'Em transporte',
        self::PICKUP => 'Aguardando retirada',
        self::DELIVERED => 'Entregue',
        self::RETURNING => 'Em devolução',
    ];

    /** Correios events before posting ("Etiqueta emitida - Aguardando postagem", "Etiqueta expirada"). */
    private const NOT_POSTED = ['etiqueta emitida', 'etiqueta expirada', 'aguardando postagem', 'prazo para postagem'];

    /** Final statuses: the order is not checked again once it reaches one of them. */
    public const FINAL = [self::DELIVERED];

    /**
     * Order status for an event. Frenet's EventType wins when it is conclusive (9 delivered, 3 returned);
     * otherwise the description decides. '' when the package has not been posted yet (no change).
     *
     * @param string $description
     * @param string $eventType
     * @return string
     */
    public static function statusFor(string $description, string $eventType = ''): string
    {
        if ($eventType === '9') {
            return self::DELIVERED;
        }
        if ($eventType === '3') {
            return self::RETURNING;
        }
        $text = mb_strtolower($description);
        foreach (self::NOT_POSTED as $needle) {
            if (str_contains($text, $needle)) {
                return '';
            }
        }
        if (str_contains($text, 'devolvido') || str_contains($text, 'devolução') || str_contains($text, 'devolucao')) {
            return self::RETURNING;
        }
        if (str_contains($text, 'entregue') && !str_contains($text, 'não entregue') && !str_contains($text, 'nao entregue')
            && !str_contains($text, 'ponto de postagem')) {
            return self::DELIVERED;
        }
        if (str_contains($text, 'aguardando retirada') || str_contains($text, 'disponível para retirada')
            || str_contains($text, 'disponivel para retirada')) {
            return self::PICKUP;
        }
        return self::IN_TRANSIT;
    }

    /**
     * Timestamp of a Frenet event date ("dd/mm/YYYY HH:ii"); 0 when unreadable.
     *
     * @param string $date
     * @return int
     */
    public static function eventTime(string $date): int
    {
        $parsed = \DateTime::createFromFormat('d/m/Y H:i', trim($date));
        return $parsed ? $parsed->getTimestamp() : 0;
    }

    /**
     * Frenet service code of an order shipped with the Frenet carrier ("frenetshipping_03298" → "03298").
     *
     * @param string $shippingMethod
     * @return string
     */
    public static function serviceCode(string $shippingMethod): string
    {
        return str_starts_with($shippingMethod, 'frenetshipping_') ? substr($shippingMethod, 15) : '';
    }
}
