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

namespace Frenet\Shipping\Test\Unit\Model\Config;

use Frenet\Shipping\Model\Config\ApiHostnameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests which API hostnames the carrier accepts for its Frenet API override.
 */
class ApiHostnameValidatorTest extends TestCase
{
    /**
     * Lists hostnames under frenet.com.br, in any letter case.
     *
     * @return array<string, array{string}>
     */
    public static function allowedHostnameProvider(): array
    {
        return [
            'production' => ['api.frenet.com.br'],
            'apex domain' => ['frenet.com.br'],
            'nested subdomain' => ['sandbox.api.frenet.com.br'],
            'upper case' => ['API.FRENET.COM.BR'],
        ];
    }

    /**
     * Lists hostnames that must never receive the Frenet token.
     *
     * @return array<string, array{string}>
     */
    public static function refusedHostnameProvider(): array
    {
        return [
            'empty' => [''],
            'other domain' => ['api.example.com'],
            'lookalike suffix' => ['api.frenet.com.br.attacker.example'],
            'lookalike prefix' => ['evilfrenet.com.br'],
            'with scheme' => ['https://api.frenet.com.br'],
            'with port' => ['api.frenet.com.br:8080'],
            'with path' => ['api.frenet.com.br/v1'],
            'with credentials' => ['user@api.frenet.com.br'],
            'localhost' => ['localhost'],
            'ip address' => ['127.0.0.1'],
        ];
    }

    /**
     * Confirms frenet.com.br and its subdomains are accepted.
     *
     * @param string $hostname
     *
     * @return void
     */
    #[DataProvider('allowedHostnameProvider')]
    public function testShouldAllowTheHostnameWhenItIsAFrenetAddress(string $hostname): void
    {
        $this->assertTrue((new ApiHostnameValidator())->isAllowed($hostname));
    }

    /**
     * Confirms anything that is not a bare frenet.com.br hostname is refused.
     *
     * @param string $hostname
     *
     * @return void
     */
    #[DataProvider('refusedHostnameProvider')]
    public function testShouldRefuseTheHostnameWhenItIsNotABareFrenetAddress(string $hostname): void
    {
        $this->assertFalse((new ApiHostnameValidator())->isAllowed($hostname));
    }
}
