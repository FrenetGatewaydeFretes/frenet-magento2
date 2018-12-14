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
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManagement
    ) {
        $this->scopeConfig     = $scopeConfig;
        $this->storeManagement = $storeManagement;
        $this->api             = \Frenet\ApiFactory::create($this->getToken());
    }
    
    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getToken()
    {
        $token = (string) $this->scopeConfig->getValue(
            'carriers/frenet_shipping/token',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManagement->getStore()->getId()
        );
        
        return $token;
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
}
