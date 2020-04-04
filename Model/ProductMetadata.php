<?php
/**
 * Copyright © MagedIn. All rights reserved.
 * See COPYING.txt for license details.
 *
 * @author Tiago Sampaio <tiago.sampaio@magedin.com>
 */
declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config;
use Magento\Framework\Composer\ComposerFactory;
use Magento\Framework\Composer\ComposerJsonFinder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Composer\ComposerInformation;

/**
 * Class ProductMetadata
 *
 * @package Frenet\Shipping\Model
 */
class ProductMetadata
{
    /**
     * @var string
     */
    const PACKAGE_NAME = 'frenet/frenet-magento2';

    /**
     * @var string
     */
    const VERSION_CACHE_KEY = 'module-frenet-shipping-version';

    /**
     * @var ComposerInformation
     */
    private $composerInformation;

    /**
     * @var string
     */
    private $version = null;

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(
        ComposerJsonFinder $composerJsonFinder,
        CacheInterface $cache
    ) {
        $this->composerJsonFinder = $composerJsonFinder;
        $this->cache = $cache;
    }

    /**
     * Get Product version
     *
     * @return string
     */
    public function getVersion()
    {
        $this->version = $this->version ?: $this->cache->load(self::VERSION_CACHE_KEY);

        if (!$this->version) {
            $this->version = $this->getPackageVersion();
            $this->cache->save($this->version, self::VERSION_CACHE_KEY, [Config::CACHE_TAG]);
        }

        return $this->version;
    }

    /**
     * Get version from module package
     *
     * @return string
     */
    private function getPackageVersion()
    {
        $packages = $this->getComposerInformation()->getInstalledMagentoPackages();

        if (isset($packages[self::PACKAGE_NAME]['version'])) {
            return $packages[self::PACKAGE_NAME]['version'];
        }

        return 'Unknown Version';
    }

    /**
     * Load composerInformation
     *
     * @return ComposerInformation
     */
    private function getComposerInformation()
    {
        if (!$this->composerInformation) {
            $directoryList = new DirectoryList(BP);
            $composerFactory = new ComposerFactory($directoryList, $this->composerJsonFinder);
            $this->composerInformation = new ComposerInformation($composerFactory);
        }

        return $this->composerInformation;
    }
}
