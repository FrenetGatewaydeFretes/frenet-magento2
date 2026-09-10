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

use Frenet\Shipping\Model\ModuleMetadata;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\FinderFactory;

/**
 * Tests that ModuleMetadata resolves the installed version from Composer and reports Unknown when absent.
 */
class ModuleMetadataTest extends TestCase
{
    /**
     * @var string
     */
    private const FIXTURE_VERSION = '2.4.9';

    /**
     * @var ComposerInformation&Stub
     */
    private Stub $composerInformation;

    /**
     * @var CacheInterface&Stub
     */
    private Stub $cache;

    /**
     * @var SerializerInterface&Stub
     */
    private Stub $serializer;

    /**
     * @var DirectoryList&Stub
     */
    private Stub $directoryList;

    /**
     * @var FinderFactory&Stub
     */
    private Stub $finderFactory;

    /**
     * @var ModuleMetadata
     */
    private ModuleMetadata $subject;

    protected function setUp(): void
    {
        $this->composerInformation = $this->createStub(ComposerInformation::class);
        $this->cache = $this->createStub(CacheInterface::class);
        $this->cache->method('load')->willReturn(false);
        $this->serializer = $this->createStub(SerializerInterface::class);
        $this->directoryList = $this->createStub(DirectoryList::class);
        $this->directoryList->method('getPath')->willReturn('/frenet-nonexistent-app-dir');
        // A real Finder pointed at a missing directory throws DirectoryNotFoundException,
        // which is exactly the "no app/code checkout" branch under test.
        $this->finderFactory = $this->createStub(FinderFactory::class);
        $this->finderFactory->method('create')->willReturnCallback(static fn (): Finder => new Finder());

        $this->subject = new ModuleMetadata(
            $this->composerInformation,
            $this->cache,
            $this->serializer,
            $this->directoryList,
            $this->finderFactory
        );
    }

    public function testShouldReturnTheComposerVersionWhenThePackageIsInstalledViaComposer(): void
    {
        $this->composerInformation->method('getInstalledMagentoPackages')->willReturn([
            ModuleMetadata::PACKAGE_NAME => [
                'name' => ModuleMetadata::PACKAGE_NAME,
                'type' => ModuleMetadata::PACKAGE_TYPE,
                'version' => self::FIXTURE_VERSION,
            ],
        ]);

        $version = $this->subject->getVersion();

        $this->assertStringContainsString(self::FIXTURE_VERSION, $version);
        $this->assertStringContainsString('(Installed Via Composer)', $version);
    }

    public function testShouldReturnUnknownWhenNoVersionSourceIsAvailable(): void
    {
        $this->composerInformation->method('getInstalledMagentoPackages')->willReturn([]);

        $this->assertSame('Unknown Module Version', $this->subject->getVersion());
    }

    public function testShouldReturnThePackageName(): void
    {
        $this->assertSame(ModuleMetadata::PACKAGE_NAME, $this->subject->getName());
    }

    public function testShouldReturnThePackageType(): void
    {
        $this->assertSame(ModuleMetadata::PACKAGE_TYPE, $this->subject->getType());
    }
}
