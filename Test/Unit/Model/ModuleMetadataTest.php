<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
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
 * Tests that ModuleMetadata reports the installed package version, preferring the Composer manifest and caching the result.
 */
class ModuleMetadataTest extends TestCase
{
    private const FIXTURE_VERSION = '2.4.9';

    private ComposerInformation&Stub $composerInformation;
    private CacheInterface&Stub $cache;
    private SerializerInterface&Stub $serializer;
    private DirectoryList&Stub $directoryList;
    private FinderFactory&Stub $finderFactory;
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
