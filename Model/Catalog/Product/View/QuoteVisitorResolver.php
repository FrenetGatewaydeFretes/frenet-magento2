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

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Combines the address Magento sees with the first forwarded client address, so visitors behind an unconfigured
 * proxy are not counted as one; a forged header only splits the visitor limit, the store limit still applies.
 */
class QuoteVisitorResolver implements QuoteVisitorResolverInterface
{
    /**
     * @param RemoteAddress $remoteAddress
     */
    public function __construct(
        private readonly RemoteAddress $remoteAddress
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(RequestInterface $request): string
    {
        $address = (string) $this->remoteAddress->getRemoteAddress();
        $forwarded = $request instanceof HttpRequest ? (string) $request->getHeader('X-Forwarded-For') : '';
        $client = trim(explode(',', $forwarded)[0]);

        if ($client === $address || filter_var($client, FILTER_VALIDATE_IP) === false) {
            return $address;
        }

        return $address . '|' . $client;
    }
}
