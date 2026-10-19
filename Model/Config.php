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
use Frenet\Shipping\Model\Config\ApiHostnameValidator;
use Frenet\Shipping\Service\RateRequestProviderInterface;
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
    /**
     * @var float
     */
    public const DEFAULT_PACKAGE_MAX_WEIGHT = 30.0;

    /**
     * @var int
     */
    public const DEFAULT_MAX_UNIT_QUANTITY = 5000;

    /**
     * @var string
     */
    public const DEFAULT_DEBUG_FILENAME = 'frenet_request_debug.log';

    /**
     * @var float
     */
    public const DEFAULT_API_TIMEOUT = 30.0;

    /**
     * @var float
     */
    public const MAX_API_TIMEOUT = 120.0;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ApiHostnameValidator $apiHostnameValidator,
        private readonly RateRequestProviderInterface $rateRequestProvider
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
        $hostname = strtolower(trim((string) $this->getCarrierConfig('api_hostname', $store)));

        return $this->apiHostnameValidator->isAllowed($hostname) ? $hostname : '';
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
    public function getApiTimeout($store = null): float
    {
        $timeout = (float) $this->getCarrierConfig('api_timeout', $store);

        return $timeout > 0 ? min($timeout, self::MAX_API_TIMEOUT) : self::DEFAULT_API_TIMEOUT;
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
        $weight = (float) $this->getCarrierConfig('package_max_weight', $store);

        return $weight > 0 ? $weight : self::DEFAULT_PACKAGE_MAX_WEIGHT;
    }

    /**
     * @inheritDoc
     */
    public function getMaxUnitQuantity($store = null): int
    {
        $quantity = (int) $this->getCarrierConfig('max_unit_quantity', $store);

        return $quantity > 0 ? $quantity : self::DEFAULT_MAX_UNIT_QUANTITY;
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
        $filename = trim((string) $this->getCarrierConfig('debug_filename', $store));

        return preg_match('/^\w[\w.-]*\.log$/', $filename) === 1 ? $filename : self::DEFAULT_DEBUG_FILENAME;
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
     * Resolves the given store, else the store being quoted, else the current one, falling back to the default view.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return StoreInterface|null
     */
    private function getStore($store = null)
    {
        $store ??= $this->rateRequestProvider->getStoreId();

        try {
            return $this->storeManager->getStore($store);
        } catch (NoSuchEntityException $exception) {
            return $this->storeManager->getDefaultStoreView();
        }
    }
}
