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

use \Magento\Store\Model\ScopeInterface;

/**
 * Class Config
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
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return bool
     */
    public function isActive($store = null)
    {
        return (bool) $this->getCarrierConfig('active', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    public function getToken($store = null)
    {
        return $this->getCarrierConfig('token', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    public function getWeightAttribute($store = null)
    {
        return $this->getCarrierConfig('attributes_mapping/weight_attribute', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    public function getHeightAttribute($store = null)
    {
        return $this->getCarrierConfig('attributes_mapping/height_attribute', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    public function getLengthAttribute($store = null)
    {
        return $this->getCarrierConfig('attributes_mapping/length_attribute', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    public function getWidthAttribute($store = null)
    {
        return $this->getCarrierConfig('attributes_mapping/width_attribute', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return float
     */
    public function getDefaultWeight($store = null)
    {
        return (float) $this->getCarrierConfig('default_measurements/default_weight', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return float
     */
    public function getDefaultHeight($store = null)
    {
        return (float) $this->getCarrierConfig('default_measurements/default_height', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return float
     */
    public function getDefaultLength($store = null)
    {
        return (float) $this->getCarrierConfig('default_measurements/default_length', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return float
     */
    public function getDefaultWidth($store = null)
    {
        return (float) $this->getCarrierConfig('default_measurements/default_width', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return int
     */
    public function getAdditionalLeadTime($store = null)
    {
        return (int) $this->getCarrierConfig('additional_lead_time', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return bool
     */
    public function canShowShippingForecast($store = null)
    {
        return (bool) $this->getCarrierConfig('show_shipping_forecast', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return bool
     */
    public function getShippingForecastMessage($store = null)
    {
        return (string) $this->getCarrierConfig('shipping_forecast_message', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return bool
     */
    public function isMultiQuoteEnabled($store = null)
    {
        return (bool) $this->getCarrierConfig('multi_quote', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return bool
     */
    public function isDebugModeEnabled($store = null)
    {
        return (bool) $this->getCarrierConfig('debug', $store);
    }

    /**
     * @param string|int|\Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    public function getDebugFilename($store = null)
    {
        return (string) $this->getCarrierConfig('debug_filename', $store);
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
