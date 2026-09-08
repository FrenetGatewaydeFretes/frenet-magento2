<?php
/**
 * Frenet_Shipping
 *
 * @vendor    Frenet
 * @package   Shipping
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@frenet.com.br>
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Estimates how many days a quoted service takes, combining the carrier forecast, product lead time and the store extra.
 */
interface DeliveryTimeCalculatorInterface
{
    /**
     * Returns the total delivery time in days for the given quoted service.
     *
     * @param ServiceInterface $service
     *
     * @return int
     * @throws LocalizedException
     */
    public function calculate(ServiceInterface $service): int;
}
