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

namespace Frenet\Shipping\Model\Config;

/**
 * Accepts only Frenet hostnames for the API hostname override, so the token is never sent to another host.
 */
class ApiHostnameValidator
{
    /**
     * @var string
     */
    private const PATTERN = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*frenet\.com\.br$/';

    /**
     * Checks whether the hostname is frenet.com.br or one of its subdomains, with no scheme, port or path.
     *
     * @param string $hostname
     *
     * @return bool
     */
    public function isAllowed(string $hostname): bool
    {
        return preg_match(self::PATTERN, strtolower(trim($hostname))) === 1;
    }
}
