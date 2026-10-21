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

namespace Frenet\Shipping\Model\Catalog\Product\View;

/**
 * Caps the product page quotes per minute so a script cannot spend the store's Frenet API quota.
 */
interface QuoteRateLimiterInterface
{
    /**
     * Counts one product page quote for the visitor and tells whether it is still within the per-minute limits.
     *
     * @param string $visitor
     *
     * @return bool
     */
    public function isAllowed(string $visitor): bool;
}
