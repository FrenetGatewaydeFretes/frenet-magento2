<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Frenet\Shipping\Model\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Settings of the labels feature (carriers/frenetshipping/labels/*). The customer token defaults to the token
 * of the Frenet shipping method, so most stores only paste the partner token.
 */
class LabelsConfig
{
    public const PATH = 'carriers/frenetshipping/labels/';
    public const DEFAULT_API_URL = 'https://whitelabel.frenet.com.br/v1';
    public const JOURNEY_HERE = 'here';
    public const JOURNEY_PANEL = 'panel';
    public const PANEL_URL = 'https://painel.frenet.com.br/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * Whether the store turned labels on.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->value('active') === '1';
    }

    /**
     * Customer token: the labels override, otherwise the token of the shipping method.
     *
     * @return string
     */
    public function storeToken(): string
    {
        $token = trim($this->value('store_token'));
        return $token !== '' ? $token : trim((string) $this->config->getToken());
    }

    /**
     * Partner token (x-partner-token).
     *
     * @return string
     */
    public function partnerToken(): string
    {
        return trim($this->value('partner_token'));
    }

    /**
     * Enabled and both tokens present.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isEnabled() && $this->partnerToken() !== '' && $this->storeToken() !== '';
    }

    /**
     * WhiteLabel API base URL without the trailing slash.
     *
     * @return string
     */
    public function apiUrl(): string
    {
        return rtrim($this->value('api_url') ?: self::DEFAULT_API_URL, '/');
    }

    /**
     * Whether the API URL is not the real Frenet one (local test simulator).
     *
     * @return bool
     */
    public function isSimulated(): bool
    {
        return stripos($this->apiUrl(), self::DEFAULT_API_URL) !== 0;
    }

    /**
     * "here" (buy and print in this admin) or "panel" (send to the Frenet panel).
     *
     * @return string
     */
    public function journey(): string
    {
        return $this->value('journey') === self::JOURNEY_PANEL ? self::JOURNEY_PANEL : self::JOURNEY_HERE;
    }

    /**
     * "price" or "time".
     *
     * @return string
     */
    public function defaultService(): string
    {
        return $this->value('default_service') === 'time' ? 'time' : 'price';
    }

    /**
     * Whether the best option comes selected instead of the customer's choice.
     *
     * @return bool
     */
    public function autoBest(): bool
    {
        return $this->value('auto_best') === '1';
    }

    /**
     * Whether labels bought here are paid at once with the wallet.
     *
     * @return bool
     */
    public function walletPayment(): bool
    {
        return $this->value('wallet_payment') !== '0';
    }

    /**
     * Whether batch printing is allowed.
     *
     * @return bool
     */
    public function batchPrint(): bool
    {
        return $this->value('batch_print') !== '0';
    }

    /**
     * Label format sent as x-printing-format (A4 or 10x15).
     *
     * @return string
     */
    public function printingFormat(): string
    {
        return $this->value('printing_format') === '10x15' ? '10x15' : 'A4';
    }

    /**
     * Any store config value (store information, locale...).
     *
     * @param string $path
     * @param int|null $storeId
     * @return string
     */
    public function storeValue(string $path, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            $path,
            $storeId ? \Magento\Store\Model\ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $storeId
        );
    }

    /**
     * Raw labels setting.
     *
     * @param string $key
     * @return string
     */
    private function value(string $key): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH . $key);
    }
}
