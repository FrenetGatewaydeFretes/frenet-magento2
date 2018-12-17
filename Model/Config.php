<?php
declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use \Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 *
 * @package Frenet\Shipping\Model
 */
class Config
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }
    
    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool) $this->getCarrierConfig('active');
    }
    
    /**
     * @return string
     */
    public function getToken()
    {
        return $this->getCarrierConfig('token');
    }
    
    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    public function getOriginPostcode($store = null)
    {
        return $this->get('shipping', 'origin', 'postcode', $store);
    }
    
    /**
     * @param string                                            $field
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return mixed
     */
    public function getCarrierConfig($field, $store = null)
    {
        return $this->get('carriers', \Frenet\Shipping\Model\Carrier\Frenet::CARRIER_CODE, $field, $store);
    }
    
    /**
     * @param string                                            $section
     * @param string                                            $group
     * @param string                                            $field
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     * @param string                                            $scopeType
     *
     * @return mixed
     */
    public function get($section, $group, $field, $store = null, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        $path = implode('/', [$section, $group, $field]);
        return $this->scopeConfig->getValue($path, $scopeType, $this->getStore($store));
    }
    
    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return \Magento\Store\Api\Data\StoreInterface|null
     */
    private function getStore($store = null)
    {
        try {
            return $this->storeManager->getStore($store);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
            return $this->storeManager->getDefaultStoreView();
        }
    }
}
