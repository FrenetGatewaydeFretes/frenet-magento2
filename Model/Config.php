<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

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
     * @return string
     */
    public function getWeightAttribute()
    {
        return $this->getCarrierConfig('attributes_mapping/weight_attribute');
    }

    /**
     * @return string
     */
    public function getHeightAttribute()
    {
        return $this->getCarrierConfig('attributes_mapping/height_attribute');
    }

    /**
     * @return string
     */
    public function getLengthAttribute()
    {
        return $this->getCarrierConfig('attributes_mapping/length_attribute');
    }

    /**
     * @return string
     */
    public function getWidthAttribute()
    {
        return $this->getCarrierConfig('attributes_mapping/width_attribute');
    }

    /**
     * @return float
     */
    public function getDefaultWeight()
    {
        return (float) $this->getCarrierConfig('default_measurements/default_weight');
    }

    /**
     * @return float
     */
    public function getDefaultHeight()
    {
        return (float) $this->getCarrierConfig('default_measurements/default_height');
    }

    /**
     * @return float
     */
    public function getDefaultLength()
    {
        return (float) $this->getCarrierConfig('default_measurements/default_length');
    }

    /**
     * @return float
     */
    public function getDefaultWidth()
    {
        return (float) $this->getCarrierConfig('default_measurements/default_width');
    }

    /**
     * @return int
     */
    public function getAdditionalLeadTime()
    {
        return (int) $this->getCarrierConfig('additional_lead_time');
    }

    /**
     * @return bool
     */
    public function canShowShippingForecast()
    {
        return (bool) $this->getCarrierConfig('show_shipping_forecast');
    }

    /**
     * @return bool
     */
    public function getShippingForecast()
    {
        return (string) $this->getCarrierConfig('shipping_forecast_message');
    }

    /**
     * @return bool
     */
    public function isDebugModeEnabled()
    {
        return (bool) $this->getCarrierConfig('debug');
    }

    /**
     * @return string
     */
    public function getDebugFilename()
    {
        return (string) $this->getCarrierConfig('debug_filename');
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
