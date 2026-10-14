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

        $this->subject = new Config($this->scopeConfig, $storeManager);
    }

    /**
     * Lists stored values that do not express a usable limit.
     *
     * @return array<string, array{mixed}>
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
     * @param mixed $value
     *
     * @return void
     */
    #[DataProvider('unusableLimitProvider')]
    public function testShouldFallBackToTheDefaultPackageMaxWeight(mixed $value): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/package_max_weight')->willReturn($value);

        $this->assertSame(Config::DEFAULT_PACKAGE_MAX_WEIGHT, $this->subject->getPackageMaxWeight());
    }

    /**
     * Confirms an unusable unit quantity falls back to the 5000 default instead of zeroing every item.
     *
     * @param mixed $value
     *
     * @return void
     */
    #[DataProvider('unusableLimitProvider')]
    public function testShouldFallBackToTheDefaultMaxUnitQuantity(mixed $value): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/max_unit_quantity')->willReturn($value);

        $this->assertSame(Config::DEFAULT_MAX_UNIT_QUANTITY, $this->subject->getMaxUnitQuantity());
    }

    /**
     * Lists debug filenames that would leave var/log or produce a non-log file.
     *
     * @return array<string, array{mixed}>
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
     * @param mixed $value
     *
     * @return void
     */
    #[DataProvider('unsafeDebugFilenameProvider')]
    public function testShouldFallBackToTheDefaultDebugFilename(mixed $value): void
    {
        $this->scopeConfig->method('getValue')->with('carriers/frenetshipping/debug_filename')->willReturn($value);

        $this->assertSame(Config::DEFAULT_DEBUG_FILENAME, $this->subject->getDebugFilename());
    }

    /**
     * Confirms a plain log file name is kept as configured.
     *
     * @return void
     */
    public function testShouldKeepAValidDebugFilename(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('frenet-2026.log');

        $this->assertSame('frenet-2026.log', $this->subject->getDebugFilename());
    }

    /**
     * Confirms configured positive limits are returned as they are.
     *
     * @return void
     */
    public function testShouldReturnTheConfiguredLimits(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(fn (string $path) => match ($path) {
            'carriers/frenetshipping/package_max_weight' => '12.5',
            'carriers/frenetshipping/max_unit_quantity' => '80',
        });

        $this->assertSame(12.5, $this->subject->getPackageMaxWeight());
        $this->assertSame(80, $this->subject->getMaxUnitQuantity());
    }
}
