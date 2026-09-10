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
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\FinderFactory;

/**
 * Resolves the human-readable module version shown in the admin, from the Composer manifest or an app/code checkout.
 */
class ModuleMetadata
{
    /**
     * @var string
     */
    public const PACKAGE_NAME = 'frenet/frenet-magento2';

    /**
     * @var string
     */
    public const PACKAGE_TYPE = 'magento-module';

    /**
     * @var string
     */
    public const VERSION_CACHE_KEY = 'module-frenet-shipping-version';

    /**
     * @var string|null
     */
    private $version = null;

    /**
     * @var array
     */
    private $package = [];

    public function __construct(
        private readonly ComposerInformation $composerInformation,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly DirectoryList $directoryList,
        private readonly FinderFactory $finderFactory
    ) {
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
     * Returns the module version label, reading it from cache or resolving and caching it on first call.
     *
     * @return string
     */
    public function getVersion(): string
    {
        $this->version = $this->version ?: $this->cache->load(self::VERSION_CACHE_KEY);

        if (!$this->version) {
            $this->version = (string) $this->getPackageVersion();
            $this->cache->save($this->version, self::VERSION_CACHE_KEY, [Config::CACHE_TAG]);
        }

        return (string) $this->version;
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

        $finder = $this->finderFactory->create();

        try {
            $finder->files()->name('composer.json')->depth(0)->in($moduleDir);
        } catch (DirectoryNotFoundException $exception) {
            return [];
        }

        if (!$finder->hasResults()) {
            return [];
        }

        $content = '';

        foreach ($finder as $file) {
            $content = $file->getContents();
            break;
        }

        return (array) $this->serializer->unserialize($content);
    }
}
