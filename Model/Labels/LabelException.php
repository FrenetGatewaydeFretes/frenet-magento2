<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\Exception\LocalizedException;

/**
 * A Frenet labels operation failed; the message is safe to show to the admin user.
 */
class LabelException extends LocalizedException
{
}
