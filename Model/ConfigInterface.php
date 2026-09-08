<?php
/**
 * Frenet_Shipping
 *
 * @vendor    Frenet
 * @package   Shipping
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@frenet.com.br>
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Typed reader for the Frenet carrier's stored configuration, resolving every value against the effective store scope.
 */
interface ConfigInterface
{
    /**
     * Checks whether the Frenet shipping method is active.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return bool
     */
    public function isActive($store = null): bool;

    /**
     * Returns the Frenet API token.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getToken($store = null): string;

    /**
     * Returns the configured Frenet API hostname override, or an empty string when unset.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getApiHostname($store = null): string;

    /**
     * Returns the configured Frenet API protocol override ('http'|'https'), or an empty string when unset.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getApiProtocol($store = null): string;

    /**
     * Returns the product attribute code mapped to weight.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getWeightAttribute($store = null): string;

    /**
     * Returns the product attribute code mapped to height.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getHeightAttribute($store = null): string;

    /**
     * Returns the product attribute code mapped to length.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getLengthAttribute($store = null): string;

    /**
     * Returns the product attribute code mapped to width.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getWidthAttribute($store = null): string;

    /**
     * Returns the default weight used when a product has none.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return float
     */
    public function getDefaultWeight($store = null): float;

    /**
     * Returns the default height used when a product has none.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return float
     */
    public function getDefaultHeight($store = null): float;

    /**
     * Returns the default length used when a product has none.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return float
     */
    public function getDefaultLength($store = null): float;

    /**
     * Returns the default width used when a product has none.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return float
     */
    public function getDefaultWidth($store = null): float;

    /**
     * Returns the additional lead time, in days, added to the delivery estimate.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return int
     */
    public function getAdditionalLeadTime($store = null): int;

    /**
     * Checks whether the shipping forecast message should be shown.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return bool
     */
    public function canShowShippingForecast($store = null): bool;

    /**
     * Returns the shipping forecast message template.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getShippingForecastMessage($store = null): string;

    /**
     * Checks whether multi-quote (package splitting) is enabled.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return bool
     */
    public function isMultiQuoteEnabled($store = null): bool;

    /**
     * Returns the maximum weight allowed per shipping package, in kilograms.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return float
     */
    public function getPackageMaxWeight($store = null): float;

    /**
     * Returns the maximum quantity of a single cart item considered when building shipping packages.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return int
     */
    public function getMaxUnitQuantity($store = null): int;

    /**
     * Checks whether debug mode is enabled.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return bool
     */
    public function isDebugModeEnabled($store = null): bool;

    /**
     * Returns the debug log filename.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getDebugFilename($store = null): string;

    /**
     * Checks whether the product-page shipping quote widget is enabled.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return bool
     */
    public function isProductQuoteEnabled($store = null): bool;

    /**
     * Checks whether the given product type may use the product-page shipping quote widget.
     *
     * @param string                         $productTypeId
     * @param string|int|StoreInterface|null $store
     *
     * @return bool
     */
    public function isProductQuoteAllowed(string $productTypeId, $store = null): bool;

    /**
     * Returns the product types allowed to use the product-page shipping quote widget.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string[]
     */
    public function getProductQuoteProductTypes($store = null): array;

    /**
     * Returns the shipping origin postcode.
     *
     * @param string|int|StoreInterface|null $store
     *
     * @return string
     */
    public function getOriginPostcode($store = null): string;

    /**
     * Returns a carrier-scoped configuration value.
     *
     * @param string                         $field
     * @param string|int|StoreInterface|null $store
     *
     * @return mixed
     */
    public function getCarrierConfig($field, $store = null): mixed;
}
