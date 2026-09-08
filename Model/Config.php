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
 * Class Config
 */
class Config
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Checks whether the Frenet shipping method is active.
     *
     * @param string|int|StoreInterface $store
     *
     * @return bool
     */
    public function isActive($store = null)
    {
        return (bool) $this->getCarrierConfig('active', $store);
    }

    /**
     * Returns the Frenet API token.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getToken($store = null)
    {
        return $this->getCarrierConfig('token', $store);
    }

    /**
     * Returns the configured Frenet API hostname override, or an empty string when unset.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getApiHostname($store = null): string
    {
        return trim((string) $this->getCarrierConfig('api_hostname', $store));
    }

    /**
     * Returns the configured Frenet API protocol override ('http'|'https'), or an empty string when unset.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getApiProtocol($store = null): string
    {
        return strtolower(trim((string) $this->getCarrierConfig('api_protocol', $store)));
    }

    /**
     * Returns the product attribute code mapped to weight.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getWeightAttribute($store = null)
    {
        return $this->getCarrierConfig('attributes_mapping/weight_attribute', $store);
    }

    /**
     * Returns the product attribute code mapped to height.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getHeightAttribute($store = null)
    {
        return $this->getCarrierConfig('attributes_mapping/height_attribute', $store);
    }

    /**
     * Returns the product attribute code mapped to length.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getLengthAttribute($store = null)
    {
        return $this->getCarrierConfig('attributes_mapping/length_attribute', $store);
    }

    /**
     * Returns the product attribute code mapped to width.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getWidthAttribute($store = null)
    {
        return $this->getCarrierConfig('attributes_mapping/width_attribute', $store);
    }

    /**
     * Returns the default weight used when a product has none.
     *
     * @param string|int|StoreInterface $store
     *
     * @return float
     */
    public function getDefaultWeight($store = null)
    {
        return (float) $this->getCarrierConfig('default_measurements/default_weight', $store);
    }

    /**
     * Returns the default height used when a product has none.
     *
     * @param string|int|StoreInterface $store
     *
     * @return float
     */
    public function getDefaultHeight($store = null)
    {
        return (float) $this->getCarrierConfig('default_measurements/default_height', $store);
    }

    /**
     * Returns the default length used when a product has none.
     *
     * @param string|int|StoreInterface $store
     *
     * @return float
     */
    public function getDefaultLength($store = null)
    {
        return (float) $this->getCarrierConfig('default_measurements/default_length', $store);
    }

    /**
     * Returns the default width used when a product has none.
     *
     * @param string|int|StoreInterface $store
     *
     * @return float
     */
    public function getDefaultWidth($store = null)
    {
        return (float) $this->getCarrierConfig('default_measurements/default_width', $store);
    }

    /**
     * Returns the additional lead time, in days, added to the delivery estimate.
     *
     * @param string|int|StoreInterface $store
     *
     * @return int
     */
    public function getAdditionalLeadTime($store = null)
    {
        return (int) $this->getCarrierConfig('additional_lead_time', $store);
    }

    /**
     * Checks whether the shipping forecast message should be shown.
     *
     * @param string|int|StoreInterface $store
     *
     * @return bool
     */
    public function canShowShippingForecast($store = null)
    {
        return (bool) $this->getCarrierConfig('show_shipping_forecast', $store);
    }

    /**
     * Returns the shipping forecast message template.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getShippingForecastMessage($store = null)
    {
        return (string) $this->getCarrierConfig('shipping_forecast_message', $store);
    }

    /**
     * Checks whether multi-quote (package splitting) is enabled.
     *
     * @param string|int|StoreInterface $store
     *
     * @return bool
     */
    public function isMultiQuoteEnabled($store = null)
    {
        return (bool) $this->getCarrierConfig('multi_quote', $store);
    }

    /**
     * Returns the maximum weight allowed per shipping package, in kilograms.
     *
     * @param string|int|StoreInterface $store
     *
     * @return float
     */
    public function getPackageMaxWeight($store = null): float
    {
        return (float) $this->getCarrierConfig('package_max_weight', $store);
    }

    /**
     * Maximum quantity of a single cart item considered when building shipping packages.
     *
     * @param string|int|StoreInterface $store
     *
     * @return int
     */
    public function getMaxUnitQuantity($store = null): int
    {
        return (int) $this->getCarrierConfig('max_unit_quantity', $store);
    }

    /**
     * Checks whether debug mode is enabled.
     *
     * @param string|int|StoreInterface $store
     *
     * @return bool
     */
    public function isDebugModeEnabled($store = null)
    {
        return (bool) $this->getCarrierConfig('debug', $store);
    }

    /**
     * Returns the debug log filename.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getDebugFilename($store = null)
    {
        return (string) $this->getCarrierConfig('debug_filename', $store);
    }

    /**
     * Checks whether the product-page shipping quote widget is enabled.
     *
     * @param string|int|StoreInterface $store
     *
     * @return bool
     */
    public function isProductQuoteEnabled($store = null)
    {
        return (bool) $this->getCarrierConfig('product_quote/enabled', $store);
    }

    /**
     * Checks whether the given product type may use the product-page shipping quote widget.
     *
     * @param string                    $productTypeId
     * @param string|int|StoreInterface $store
     *
     * @return bool
     */
    public function isProductQuoteAllowed(string $productTypeId, $store = null): bool
    {
        $allowedTypes = $this->getProductQuoteProductTypes($store);
        return in_array($productTypeId, $allowedTypes, true);
    }

    /**
     * Returns the product types allowed to use the product-page shipping quote widget.
     *
     * @param string|int|StoreInterface $store
     *
     * @return array
     */
    public function getProductQuoteProductTypes($store = null): array
    {
        return explode(
            ',',
            (string) $this->getCarrierConfig('product_quote/product_types', $store)
        );
    }

    /**
     * Returns the shipping origin postcode.
     *
     * @param string|int|StoreInterface $store
     *
     * @return string
     */
    public function getOriginPostcode($store = null)
    {
        return $this->get('shipping', 'origin', 'postcode', $store);
    }

    /**
     * Returns a carrier-scoped configuration value.
     *
     * @param string                    $field
     * @param string|int|StoreInterface $store
     *
     * @return mixed
     */
    public function getCarrierConfig($field, $store = null)
    {
        return $this->get('carriers', Frenet::CARRIER_CODE, $field, $store);
    }

    /**
     * Returns a configuration value for the given section, group and field.
     *
     * @param string                    $section
     * @param string                    $group
     * @param string                    $field
     * @param string|int|StoreInterface $store
     * @param string                    $scopeType
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
     * @param string|int|StoreInterface $store
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
