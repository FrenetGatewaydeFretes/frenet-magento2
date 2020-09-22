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
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Serialize\Serializer\Json;
use Symfony\Component\Finder\Finder;

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

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    public function __construct(
        ComposerInformation $composerInformation,
        CacheInterface $cache,
        Json $serializer,
        DirectoryList $directoryList
    ) {
        $this->composerInformation = $composerInformation;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->directoryList = $directoryList;
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
        $mageAppDir = $this->directoryList->getPath(DirectoryList::APP);
        $moduleDir = implode(DIRECTORY_SEPARATOR, [$mageAppDir, 'code', 'Frenet', 'Shipping']);

        $finder = new Finder();
        $finder->files()->name('composer.json')->depth(0)->in($moduleDir);

        if (!$finder->hasResults()) {
            return [];
        }

        $content = [];

        /** @var  $file */
        foreach ($finder as $file) {
            $content = $file->getContents();
            break;
        }
        return (array) $this->serializer->unserialize($content);
    }
}
