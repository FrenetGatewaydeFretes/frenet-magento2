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
 * Boots the frenet-php API client once per token and applies the quoted store's connection/debug settings to it.
 */
class ApiService implements ApiServiceInterface
{
    /**
     * @var ApiInterface|null
     */
    private ?ApiInterface $api = null;

    /**
     * @var string|null
     */
    private ?string $token = null;

    /**
     * @param DirectoryList $directoryList
     * @param ConfigInterface $config
     */
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
     * Initializes the API client, again whenever the quoted store uses a different token.
     *
     * @throws DependencyException
     * @throws NotFoundException
     * @throws WrongDataTypeException
     * @throws FileSystemException
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function init(): void
    {
        $token = $this->config->getToken();

        if ($this->api !== null && $token === $this->token) {
            return;
        }

        $this->api = ApiFactory::create($token);
        $this->token = $token;

        $this->initConnection();
        $this->initLogs();
    }

    /**
     * Applies the optional API hostname/protocol overrides and the request timeout from the store configuration.
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

        $this->api->config()->service()->setTimeout($this->config->getApiTimeout());
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
