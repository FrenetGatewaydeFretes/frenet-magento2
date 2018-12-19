<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Frenet\Shipping\Api\ApiServiceInterface;

/**
 * Class ApiService
 *
 * @package Frenet\Shipping\Model
 */
class ApiService implements ApiServiceInterface
{
    /**
     * @var \Frenet\ApiInterface
     */
    private $api;
    
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManagement;
    
    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    private $directoryList;
    
    /**
     * @var Config
     */
    private $config;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        Config $config
    ) {
        $this->config          = $config;
        $this->scopeConfig     = $scopeConfig;
        $this->storeManagement = $storeManagement;
        $this->directoryList   = $directoryList;
        $this->api             = \Frenet\ApiFactory::create($config->getToken());
        
        $this->init();
    }
    
    /**
     * @inheritdoc
     */
    public function postcode()
    {
        return $this->api->postcode();
    }
    
    /**
     * @inheritdoc
     */
    public function tracking()
    {
        return $this->api->tracking();
    }
    
    /**
     * @inheritdoc
     */
    public function shipping()
    {
        return $this->api->shipping();
    }
    
    /**
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function init()
    {
        if (true == $this->config->isDebugModeEnabled()) {
            $this->api
                ->config()
                ->debugger()
                ->isEnabled(true)
                ->setFilePath($this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::LOG))
                ->setFilename($this->config->getDebugFilename());
        }
    }
}
