<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Frenet refused the default drop-off point by API for this carrier: the screen links to the panel page instead.
 */
class DropoffRefusedException extends LabelException
{
}
