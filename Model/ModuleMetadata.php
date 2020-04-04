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
use Magento\Framework\Composer\ComposerInformation;

/**
 * Class ModuleMetadata
 *
 * @package Frenet\Shipping\Model
 */
class ModuleMetadata
{
    /**
     * @var string
     */
    const PACKAGE_NAME = 'frenet/frenet-magento2';

    /**
     * @var string
     */
    const PACKAGE_TYPE = 'magento-module';

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
     * @var array
     */
    private $package = [];

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(
        ComposerInformation $composerInformation,
        CacheInterface $cache
    ) {
        $this->composerInformation = $composerInformation;
        $this->cache = $cache;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return self::PACKAGE_NAME;
    }

    /**
     * @return string
     */
    public function getType() : string
    {
        return self::PACKAGE_TYPE;
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
        $package = $this->getPackage();

        if (isset($package['version'])) {
            return $package['version'];
        }

        return __('Unknown Module Version');
    }

    /**
     * @return array
     */
    private function getPackage() : array
    {
        $this->preparePackage();

        if ($this->package) {
            return $this->package;
        }

        return [];
    }

    /**
     * @return void
     */
    private function preparePackage()
    {
        if ($this->package) {
            return;
        }

        $packages = $this->composerInformation->getInstalledMagentoPackages();

        if (isset($packages[self::PACKAGE_NAME])) {
            $this->package = $packages[self::PACKAGE_NAME];
        }
    }
}
