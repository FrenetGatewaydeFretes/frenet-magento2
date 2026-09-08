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

namespace Frenet\Shipping\Model;

use DI\DependencyException;
use DI\NotFoundException;
use Frenet\ApiFactory;
use Frenet\ApiInterface;
use Frenet\Command\PostcodeInterface;
use Frenet\Command\ShippingInterface;
use Frenet\Command\TrackingInterface;
use Frenet\Framework\Exception\WrongDataTypeException;
use Frenet\Shipping\Model\Config\Source\ApiProtocol;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;

/**
 * Boots the frenet-php API client once per request and applies the store's connection/debug settings to it.
 */
class ApiService implements ApiServiceInterface
{
    private ?ApiInterface $api = null;

    private bool $isInitialized = false;

    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function postcode(): PostcodeInterface
    {
        $this->init();
        return $this->api->postcode();
    }

    /**
     * @inheritDoc
     */
    public function tracking(): TrackingInterface
    {
        $this->init();
        return $this->api->tracking();
    }

    /**
     * @inheritDoc
     */
    public function shipping(): ShippingInterface
    {
        $this->init();
        return $this->api->shipping();
    }

    /**
     * Initializes the API Service.
     *
     * @throws DependencyException
     * @throws NotFoundException
     * @throws WrongDataTypeException
     * @throws FileSystemException
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function init(): void
    {
        if ($this->isInitialized) {
            return;
        }

        $this->api = ApiFactory::create($this->config->getToken());

        $this->initConnection();
        $this->initLogs();
        $this->isInitialized = true;
    }

    /**
     * Applies the optional API hostname/protocol overrides from the store configuration.
     *
     * @throws WrongDataTypeException
     */
    private function initConnection(): void
    {
        $hostname = $this->config->getApiHostname();
        $protocol = $this->config->getApiProtocol();

        if ($hostname !== '') {
            $this->api->config()->service()->setHostname($hostname);
        }

        if (in_array($protocol, [ApiProtocol::HTTP, ApiProtocol::HTTPS], true)) {
            $this->api->config()->service()->setProtocol($protocol);
        }
    }

    /**
     * Enables the frenet-php file debugger when the module debug mode is on.
     *
     * @throws FileSystemException
     */
    private function initLogs(): void
    {
        if ($this->config->isDebugModeEnabled()) {
            $this->api
                ->config()
                ->debugger()
                ->isEnabled(true)
                ->setFilePath($this->directoryList->getPath(DirectoryList::LOG))
                ->setFilename($this->config->getDebugFilename());
        }
    }
}
