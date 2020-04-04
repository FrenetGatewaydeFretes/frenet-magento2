<?php
/**
 * Copyright © MagedIn. All rights reserved.
 * See COPYING.txt for license details.
 *
 * @author Tiago Sampaio <tiago.sampaio@magedin.com>
 */
declare(strict_types = 1);

namespace Frenet\Shipping\Test\Unit\Model;

use Frenet\Shipping\Model\ProductMetadata;
use Frenet\Shipping\Test\Unit\TestCase;
use Magento\Framework\Composer\ComposerInformation;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class ProductMetadata
 *
 * @package Frenet\Shipping\Test\Unit\Model
 */
class ProductMetadataTest extends TestCase
{
    /**
     * @var string
     */
    private $version = '2.1.4';

    /**
     * @var ProductMetadata
     */
    private $productMetadata;

    /**
     * @var ComposerInformation | MockObject
     */
    private $composerInformation;

    protected function setUp()
    {
        $this->composerInformation = $this->createMock(ComposerInformation::class);
        $this->productMetadata = $this->getObjectManager()->getObject(ProductMetadata::class, [
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
        $this->assertEquals(ProductMetadata::PACKAGE_NAME, $this->productMetadata->getName());
    }

    /**
     * @test
     */
    public function getPackageType()
    {
        $this->prepareComposerInformation();
        $this->assertEquals(ProductMetadata::PACKAGE_TYPE, $this->productMetadata->getType());
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
            ProductMetadata::PACKAGE_NAME => [
                'name' => ProductMetadata::PACKAGE_NAME,
                'type' => ProductMetadata::PACKAGE_TYPE,
                'version' => $this->version
            ]
        ];

        return $packageInformation;
    }
}
