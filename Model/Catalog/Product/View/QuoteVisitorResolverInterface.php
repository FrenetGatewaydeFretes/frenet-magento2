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

use Magento\Framework\App\RequestInterface;

/**
 * Tells product page quote requests apart per visitor for the rate limit, also behind proxies and CDNs.
 */
interface QuoteVisitorResolverInterface
{
    /**
     * Returns the identity the rate limit counts the request under.
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    public function resolve(RequestInterface $request): string;
}
