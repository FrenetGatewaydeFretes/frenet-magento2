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

namespace Frenet\Shipping\Test\Unit\Model;

use Frenet\Shipping\Model\Config;
use Frenet\Shipping\Model\Config\ApiHostnameValidator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the package limits read from the carrier configuration and their fallback to the documented defaults.
 */
#[AllowMockObjectsWithoutExpectations]
class ConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&MockObject
     */
    private MockObject $scopeConfig;

    /**
     * @var Config
     */
    private Config $subject;

    /**
     * Builds the config reader over a mocked scope config and store manager.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        $this->subject = new Config($this->scopeConfig, $storeManager, new ApiHostnameValidator());
    }

    /**
     * Lists stored values that do not express a usable limit.
     *
     * @return array<string, array{string|null}>
     */
    public static function unusableLimitProvider(): array
    {
        return [
            'saved empty' => [''],
            'not saved' => [null],
            'zero' => ['0'],
            'negative' => ['-5'],
        ];
    }

    /**
     * Confirms an unusable package weight falls back to the 30 kg default instead of discarding every item.
     *
     * @param string|null $value
     *
     * @return void
     */
    #[DataProvider('unusableLimitProvider')]
    public function testShouldFallBackToTheDefaultPackageMaxWeightWhenTheStoredValueIsUnusable(?string $value): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/package_max_weight')->willReturn($value);

        $this->assertSame(Config::DEFAULT_PACKAGE_MAX_WEIGHT, $this->subject->getPackageMaxWeight());
    }

    /**
     * Confirms an unusable unit quantity falls back to the 5000 default instead of zeroing every item.
     *
     * @param string|null $value
     *
     * @return void
     */
    #[DataProvider('unusableLimitProvider')]
    public function testShouldFallBackToTheDefaultMaxUnitQuantityWhenTheStoredValueIsUnusable(?string $value): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/max_unit_quantity')->willReturn($value);

        $this->assertSame(Config::DEFAULT_MAX_UNIT_QUANTITY, $this->subject->getMaxUnitQuantity());
    }

    /**
     * Lists debug filenames that would leave var/log or produce a non-log file.
     *
     * @return array<string, array{string|null}>
     */
    public static function unsafeDebugFilenameProvider(): array
    {
        return [
            'not saved' => [null],
            'saved empty' => [''],
            'parent directory' => ['../../pub/frenet.log'],
            'absolute path' => ['/var/www/html/pub/frenet.log'],
            'windows path' => ['..\\pub\\frenet.log'],
            'php file' => ['frenet.php'],
            'no extension' => ['frenet'],
            'hidden dot file' => ['.log'],
        ];
    }

    /**
     * Confirms an unsafe debug filename falls back to the default log file inside var/log.
     *
     * @param string|null $value
     *
     * @return void
     */
    #[DataProvider('unsafeDebugFilenameProvider')]
    public function testShouldFallBackToTheDefaultDebugFilenameWhenTheStoredNameIsUnsafe(?string $value): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/debug_filename')->willReturn($value);

        $this->assertSame(Config::DEFAULT_DEBUG_FILENAME, $this->subject->getDebugFilename());
    }

    /**
     * Confirms a plain log file name is kept as configured.
     *
     * @return void
     */
    public function testShouldKeepTheDebugFilenameWhenItIsAPlainLogFile(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('frenet-2026.log');

        $this->assertSame('frenet-2026.log', $this->subject->getDebugFilename());
    }

    /**
     * Confirms a frenet.com.br hostname override is returned normalized.
     *
     * @return void
     */
    public function testShouldReturnTheApiHostnameNormalizedWhenItIsAFrenetAddress(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('carriers/frenetshipping/api_hostname')
            ->willReturn(' API.Frenet.com.br ');

        $this->assertSame('api.frenet.com.br', $this->subject->getApiHostname());
    }

    /**
     * Confirms a hostname outside frenet.com.br, such as one injected through env.php, falls back to the default.
     *
     * @return void
     */
    public function testShouldIgnoreTheApiHostnameWhenItIsOutsideFrenet(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('carriers/frenetshipping/api_hostname')
            ->willReturn('attacker.example');

        $this->assertSame('', $this->subject->getApiHostname());
    }

    /**
     * Confirms an unusable API timeout falls back to the 30 second default instead of waiting forever.
     *
     * @param string|null $value
     *
     * @return void
     */
    #[DataProvider('unusableLimitProvider')]
    public function testShouldFallBackToTheDefaultApiTimeoutWhenTheStoredValueIsUnusable(?string $value): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/api_timeout')->willReturn($value);

        $this->assertSame(Config::DEFAULT_API_TIMEOUT, $this->subject->getApiTimeout());
    }

    /**
     * Confirms a configured API timeout is returned in seconds.
     *
     * @return void
     */
    public function testShouldReturnTheApiTimeoutWhenItIsWithinTheLimit(): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/api_timeout')->willReturn('7.5');

        $this->assertSame(7.5, $this->subject->getApiTimeout());
    }

    /**
     * Lists API timeouts above the 120 second cap, including one that overflows to infinity.
     *
     * @return array<string, array{string}>
     */
    public static function oversizedApiTimeoutProvider(): array
    {
        return [
            'above the cap' => ['500'],
            'overflows to infinity' => ['1e400'],
        ];
    }

    /**
     * Confirms an API timeout above the cap is limited to 120 seconds instead of waiting without limit.
     *
     * @param string $value
     *
     * @return void
     */
    #[DataProvider('oversizedApiTimeoutProvider')]
    public function testShouldCapTheApiTimeoutWhenItExceedsTheMaximum(string $value): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/api_timeout')->willReturn($value);

        $this->assertSame(Config::MAX_API_TIMEOUT, $this->subject->getApiTimeout());
    }

    /**
     * Confirms configured positive limits are returned as they are.
     *
     * @return void
     */
    public function testShouldReturnTheConfiguredLimitsWhenTheyArePositive(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(fn (string $path) => match ($path) {
            'carriers/frenetshipping/package_max_weight' => '12.5',
            'carriers/frenetshipping/max_unit_quantity' => '80',
        });

        $this->assertSame(12.5, $this->subject->getPackageMaxWeight());
        $this->assertSame(80, $this->subject->getMaxUnitQuantity());
    }
}
