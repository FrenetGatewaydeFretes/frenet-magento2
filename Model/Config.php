<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model;

use Frenet\Shipping\Model\Carrier\Frenet;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reads the Frenet carrier configuration, resolving each value against the effective store scope with a safe fallback.
 */
class Config implements ConfigInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isActive($store = null): bool
    {
        return (bool) $this->getCarrierConfig('active', $store);
    }

    /**
     * @inheritDoc
     */
    public function getToken($store = null): string
    {
        return (string) $this->getCarrierConfig('token', $store);
    }

    /**
     * @inheritDoc
     */
    public function getApiHostname($store = null): string
    {
        return trim((string) $this->getCarrierConfig('api_hostname', $store));
    }

    /**
     * @inheritDoc
     */
    public function getApiProtocol($store = null): string
    {
        return strtolower(trim((string) $this->getCarrierConfig('api_protocol', $store)));
    }

    /**
     * @inheritDoc
     */
    public function getWeightAttribute($store = null): string
    {
        return (string) $this->getCarrierConfig('attributes_mapping/weight_attribute', $store);
    }

    /**
     * @inheritDoc
     */
    public function getHeightAttribute($store = null): string
    {
        return (string) $this->getCarrierConfig('attributes_mapping/height_attribute', $store);
    }

    /**
     * @inheritDoc
     */
    public function getLengthAttribute($store = null): string
    {
        return (string) $this->getCarrierConfig('attributes_mapping/length_attribute', $store);
    }

    /**
     * @inheritDoc
     */
    public function getWidthAttribute($store = null): string
    {
        return (string) $this->getCarrierConfig('attributes_mapping/width_attribute', $store);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultWeight($store = null): float
    {
        return (float) $this->getCarrierConfig('default_measurements/default_weight', $store);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultHeight($store = null): float
    {
        return (float) $this->getCarrierConfig('default_measurements/default_height', $store);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultLength($store = null): float
    {
        return (float) $this->getCarrierConfig('default_measurements/default_length', $store);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultWidth($store = null): float
    {
        return (float) $this->getCarrierConfig('default_measurements/default_width', $store);
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalLeadTime($store = null): int
    {
        return (int) $this->getCarrierConfig('additional_lead_time', $store);
    }

    /**
     * @inheritDoc
     */
    public function canShowShippingForecast($store = null): bool
    {
        return (bool) $this->getCarrierConfig('show_shipping_forecast', $store);
    }

    /**
     * @inheritDoc
     */
    public function getShippingForecastMessage($store = null): string
    {
        return (string) $this->getCarrierConfig('shipping_forecast_message', $store);
    }

    /**
     * @inheritDoc
     */
    public function isMultiQuoteEnabled($store = null): bool
    {
        return (bool) $this->getCarrierConfig('multi_quote', $store);
    }

    /**
     * @inheritDoc
     */
    public function getPackageMaxWeight($store = null): float
    {
        return (float) $this->getCarrierConfig('package_max_weight', $store);
    }

    /**
     * @inheritDoc
     */
    public function getMaxUnitQuantity($store = null): int
    {
        return (int) $this->getCarrierConfig('max_unit_quantity', $store);
    }

    /**
     * @inheritDoc
     */
    public function isDebugModeEnabled($store = null): bool
    {
        return (bool) $this->getCarrierConfig('debug', $store);
    }

    /**
     * @inheritDoc
     */
    public function getDebugFilename($store = null): string
    {
        return (string) $this->getCarrierConfig('debug_filename', $store);
    }

    /**
     * @inheritDoc
     */
    public function isProductQuoteEnabled($store = null): bool
    {
        return (bool) $this->getCarrierConfig('product_quote/enabled', $store);
    }

    /**
     * @inheritDoc
     */
    public function isProductQuoteAllowed(string $productTypeId, $store = null): bool
    {
        $allowedTypes = $this->getProductQuoteProductTypes($store);
        return in_array($productTypeId, $allowedTypes, true);
    }

    /**
     * @inheritDoc
     */
    public function getProductQuoteProductTypes($store = null): array
    {
        return explode(
            ',',
            (string) $this->getCarrierConfig('product_quote/product_types', $store)
        );
    }

    /**
     * @inheritDoc
     */
    public function getOriginPostcode($store = null): string
    {
        return (string) $this->get('shipping', 'origin', 'postcode', $store);
    }

    /**
     * @inheritDoc
     */
    public function getCarrierConfig($field, $store = null): mixed
    {
        return $this->get('carriers', Frenet::CARRIER_CODE, $field, $store);
    }

    /**
     * Returns a configuration value for the given section, group and field.
     *
     * @param string                         $section
     * @param string                         $group
     * @param string                         $field
     * @param string|int|StoreInterface|null $store
     * @param string                         $scopeType
     *
     * @return mixed
     */
    public function get($section, $group, $field, $store = null, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        $path = implode('/', [$section, $group, $field]);
        return $this->scopeConfig->getValue($path, $scopeType, $this->getStore($store));
    }

    /**
     * Resolves the store to use, falling back to the default store view.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return StoreInterface|null
     */
    private function getStore($store = null)
    {
        try {
            return $this->storeManager->getStore($store);
        } catch (NoSuchEntityException $exception) {
            return $this->storeManager->getDefaultStoreView();
        }
    }
}
