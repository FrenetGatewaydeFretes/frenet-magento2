<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * The order already has a Frenet shipment: not a label error, so it never goes to the "Notes" of the order.
 */
class ShipmentExistsException extends LabelException
{
}
