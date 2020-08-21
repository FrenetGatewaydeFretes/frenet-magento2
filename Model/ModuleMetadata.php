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

namespace Frenet\Shipping\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class ModuleMetadata
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

    /**
     * @var Json
     */
    private Json $serializer;

    public function __construct(
        ComposerInformation $composerInformation,
        CacheInterface $cache,
        Json $serializer
    ) {
        $this->composerInformation = $composerInformation;
        $this->cache = $cache;
        $this->serializer = $serializer;
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
            return $package['version'] . ' ' . __('(Installed Via Composer)');
        }

        if ($this->getLocalVersion()) {
            return $this->getLocalVersion();
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

    /**
     * @return string|null
     */
    private function getLocalVersion()
    {
        if ($this->version) {
            return $this->version;
        }

        $metadata = $this->getLocalComposerInfo();
        if (!isset($metadata['version'])) {
            return null;
        }

        $this->version = $metadata['version'] . ' ' . __('(Installed in app/code)');
        return $this->version;
    }

    /**
     * @return array
     */
    private function getLocalComposerInfo()
    {
        $composerJson = dirname(__DIR__) . '/composer.json';

        if (!file_exists($composerJson) && is_readable($composerJson)) {
            return [];
        }

        $content = file_get_contents($composerJson);
        $metadata = (array) $this->serializer->unserialize($content);

        return $metadata;
    }
}
