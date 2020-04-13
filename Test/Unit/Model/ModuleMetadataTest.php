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
declare(strict_types = 1);

namespace Frenet\Shipping\Test\Unit\Model;

use Frenet\Shipping\Model\ModuleMetadata;
use Frenet\Shipping\Test\Unit\TestCase;
use Magento\Framework\Composer\ComposerInformation;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class ModuleMetadata
 */
class ModuleMetadataTest extends TestCase
{
    /**
     * @var string
     */
    private $version = '2.1.4';

    /**
     * @var ModuleMetadata
     */
    private $productMetadata;

    /**
     * @var ComposerInformation | MockObject
     */
    private $composerInformation;

    protected function setUp()
    {
        $this->composerInformation = $this->createMock(ComposerInformation::class);
        $this->productMetadata = $this->getObjectManager()->getObject(ModuleMetadata::class, [
            'composerInformation' => $this->composerInformation
        ]);
    }

    /**
     * @test
     */
    public function getPackageVersion()
    {
        $this->prepareComposerInformation();
        $this->assertEquals($this->version, $this->productMetadata->getVersion());
    }

    /**
     * @test
     */
    public function getPackageUnknownVersion()
    {
        $this->assertEquals('Unknown Module Version', $this->productMetadata->getVersion());
    }

    /**
     * @test
     */
    public function getPackageName()
    {
        $this->prepareComposerInformation();
        $this->assertEquals(ModuleMetadata::PACKAGE_NAME, $this->productMetadata->getName());
    }

    /**
     * @test
     */
    public function getPackageType()
    {
        $this->prepareComposerInformation();
        $this->assertEquals(ModuleMetadata::PACKAGE_TYPE, $this->productMetadata->getType());
    }

    private function prepareComposerInformation()
    {
        $this->composerInformation->method('getInstalledMagentoPackages')->willReturn($this->getPackageInformation());
    }

    /**
     * @return array
     */
    private function getPackageInformation()
    {
        $packageInformation = [
            ModuleMetadata::PACKAGE_NAME => [
                'name' => ModuleMetadata::PACKAGE_NAME,
                'type' => ModuleMetadata::PACKAGE_TYPE,
                'version' => $this->version
            ]
        ];

        return $packageInformation;
    }
}
