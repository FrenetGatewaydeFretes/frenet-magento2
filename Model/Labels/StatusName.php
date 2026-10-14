<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Human name and tone of each Frenet shipment status.
 */
class StatusName
{
    /**
     * Status → [label, tone]. Tone drives the pill colour: ok, wait, bad, off.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function all(): array
    {
        return [
            1 => [(string) __('Created'), 'wait'],
            2 => [(string) __('Waiting for payment'), 'wait'],
            3 => [(string) __('Payment failed'), 'bad'],
            4 => [(string) __('Paid'), 'ok'],
            5 => [(string) __('Posted'), 'ok'],
            6 => [(string) __('Cancellation scheduled'), 'off'],
            7 => [(string) __('Cancelled'), 'off'],
            9 => [(string) __('Deleted'), 'off'],
            18 => [(string) __('Delivered at the drop-off point'), 'ok'],
        ];
    }

    /**
     * Label of one status.
     *
     * @param int $status
     * @return string
     */
    public static function label(int $status): string
    {
        return self::all()[$status][0] ?? (string) __('Status %1', $status);
    }

    /**
     * Tone of one status.
     *
     * @param int $status
     * @return string
     */
    public static function tone(int $status): string
    {
        return self::all()[$status][1] ?? 'off';
    }
}
