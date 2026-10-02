<?php
/**
 * Frenet Shipping Gateway — window.checkoutConfig.frenetCep, read by the CEP mixin of the checkout.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Checkout;

use Frenet\Shipping\Model\Extras\Settings;
use Magento\Checkout\Model\ConfigProviderInterface;

class CepConfigProvider implements ConfigProviderInterface
{
    /**
     * @param Settings $settings
     */
    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * Checkout options of the CEP lookup.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return ['frenetCep' => ['enabled' => $this->settings->cepAutofill()]];
    }
}
