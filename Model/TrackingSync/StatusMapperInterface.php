<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\TrackingSync;

/**
 * Frenet tracking event → order status. Replace it through a di.xml preference to change the mapping.
 */
interface StatusMapperInterface
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

    /** Final statuses: the order is not checked again once it reaches one of them. */
    public const FINAL = [self::DELIVERED];

    /**
     * Order status for an event; '' when the package has not been posted yet (no change).
     *
     * @param string $description
     * @param string $eventType Frenet EventType ("9" delivered, "3" returned).
     * @return string
     */
    public function statusFor(string $description, string $eventType = ''): string;

    /**
     * Timestamp of a Frenet event date ("dd/mm/YYYY HH:ii"); 0 when unreadable.
     *
     * @param string $date
     * @return int
     */
    public function eventTime(string $date): int;

    /**
     * Frenet service code of an order shipped with the Frenet carrier ("frenetshipping_03298" → "03298").
     *
     * @param string $shippingMethod
     * @return string
     */
    public function serviceCode(string $shippingMethod): string;
}
