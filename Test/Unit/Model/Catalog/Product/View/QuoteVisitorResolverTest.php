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

namespace Frenet\Shipping\Test\Unit\Model\Catalog\Product\View;

use Frenet\Shipping\Model\Catalog\Product\View\QuoteVisitorResolver;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests how the visitor identity of the quote rate limit is built from the remote and forwarded addresses.
 */
#[AllowMockObjectsWithoutExpectations]
class QuoteVisitorResolverTest extends TestCase
{
    /**
     * Lists forwarded headers with the identity expected when Magento sees the proxy address 10.0.0.2.
     *
     * @return array<string, array{string|false, string}>
     */
    public static function forwardedHeaderProvider(): array
    {
        return [
            'no header' => [false, '10.0.0.2'],
            'client behind the proxy' => ['203.0.113.5', '10.0.0.2|203.0.113.5'],
            'chain of proxies' => ['203.0.113.5, 198.51.100.7, 10.0.0.1', '10.0.0.2|203.0.113.5'],
            'ipv6 client' => ['2001:db8::1', '10.0.0.2|2001:db8::1'],
            'same address as the remote one' => ['10.0.0.2', '10.0.0.2'],
            'not an address' => ['<script>', '10.0.0.2'],
            'empty' => ['', '10.0.0.2'],
        ];
    }

    /**
     * Confirms visitors behind a proxy Magento was not told about still get one identity each.
     *
     * @param string|false $header
     * @param string $expected
     *
     * @return void
     */
    #[DataProvider('forwardedHeaderProvider')]
    public function testShouldAddTheForwardedClientToTheRemoteAddress(string|false $header, string $expected): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getHeader')->with('X-Forwarded-For')->willReturn($header);

        $this->assertSame($expected, $this->resolver('10.0.0.2')->resolve($request));
    }

    /**
     * Confirms a request without HTTP headers is identified by the remote address alone.
     *
     * @return void
     */
    public function testShouldUseTheRemoteAddressForARequestWithoutHeaders(): void
    {
        $this->assertSame(
            '203.0.113.5',
            $this->resolver('203.0.113.5')->resolve($this->createMock(RequestInterface::class))
        );
    }

    /**
     * Builds the resolver over a remote address that returns the given value.
     *
     * @param string $address
     *
     * @return QuoteVisitorResolver
     */
    private function resolver(string $address): QuoteVisitorResolver
    {
        $remoteAddress = $this->createMock(RemoteAddress::class);
        $remoteAddress->method('getRemoteAddress')->willReturn($address);

        return new QuoteVisitorResolver($remoteAddress);
    }
}
