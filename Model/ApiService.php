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

use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class ApiService
 *
 * Used for communication with the API Service.
 */
class ApiService implements ApiServiceInterface
{
    /**
     * @var \Frenet\ApiInterface
     */
    private $api;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var bool
     */
    private $isInitialized = false;

    public function __construct(
        DirectoryList $directoryList,
        Config $config
    ) {
        $this->config = $config;
        $this->directoryList = $directoryList;
    }

    /**
     * @inheritdoc
     */
    public function postcode()
    {
        $this->init();
        return $this->api->postcode();
    }

    /**
     * @inheritdoc
     */
    public function tracking()
    {
        $this->init();
        return $this->api->tracking();
    }

    /**
     * @inheritdoc
     */
    public function shipping()
    {
        $this->init();
        return $this->api->shipping();
    }

    /**
     * Initializes the API Service.
     *
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    private function init()
    {
        if (true === $this->isInitialized) {
            return;
        }

        $this->api = \Frenet\ApiFactory::create($this->config->getToken());

        $this->initLogs();
        $this->isInitialized = true;
    }

    /**
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function initLogs()
    {
        if (true == $this->config->isDebugModeEnabled()) {
            $this->api
                ->config()
                ->debugger()
                ->isEnabled(true)
                ->setFilePath($this->directoryList->getPath(DirectoryList::LOG))
                ->setFilename($this->config->getDebugFilename());
        }
    }
}
