<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Order id sent to Frenet (injected service, unit tested).
 *
 * Frenet refuses a second shipment for an order id it already used, even when that label was cancelled
 * ("Este pedido já está integrado na etiqueta ..."), so later attempts go as "000000123-2", "000000123-3".
 */
class FrenetOrderId
{
    /**
     * Order id of the given attempt (1 = the first shipment of the order).
     *
     * @param string $incrementId
     * @param int $attempt
     * @return string
     */
    public function build(string $incrementId, int $attempt): string
    {
        return $attempt > 1 ? $incrementId . '-' . $attempt : $incrementId;
    }

    /**
     * Local order number of a Frenet order id ("000000123" or "000000123-2" → "000000123").
     *
     * @param string $frenetOrderId
     * @return string
     */
    public function incrementId(string $frenetOrderId): string
    {
        return (string) preg_replace('/-\d+$/', '', $frenetOrderId);
    }
}
