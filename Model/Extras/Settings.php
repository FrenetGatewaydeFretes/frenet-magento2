<?php
/**
 * Frenet Shipping Gateway — settings of the checkout CEP lookup and the automatic tracking.
 * New keys only: carriers/frenetshipping/cep_autofill/* and carriers/frenetshipping/tracking_sync/*.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Extras;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Settings
{
    private const BASE = 'carriers/frenetshipping/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Fill the checkout address from the CEP.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function cepAutofill(?int $storeId = null): bool
    {
        return $this->flag('cep_autofill/active', $storeId);
    }

    /**
     * Fetch the Frenet tracking events of shipped orders in the background.
     *
     * @return bool
     */
    public function trackingSync(): bool
    {
        return $this->flag('tracking_sync/active');
    }

    /**
     * Move the order status according to the latest tracking event.
     *
     * @return bool
     */
    public function autoStatus(): bool
    {
        return $this->flag('tracking_sync/auto_status');
    }

    /**
     * E-mail the customer when the status changes or a code is imported.
     *
     * @return bool
     */
    public function notifyCustomer(): bool
    {
        return $this->flag('tracking_sync/notify_customer');
    }

    /**
     * Reads a yes/no setting.
     *
     * @param string $path
     * @param int|null $storeId
     * @return bool
     */
    private function flag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::BASE . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
