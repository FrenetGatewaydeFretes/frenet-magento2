<?php
/**
 * Frenet Shipping Gateway — address of a Brazilian CEP, used by the checkout to fill the address.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Api;

use Frenet\Shipping\Api\Data\CepAddressInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface CepLookupInterface
{
    /**
     * Street, district, city and state of a CEP (GET /V1/frenet/cep/:cep).
     *
     * @param string $cep
     * @return \Frenet\Shipping\Api\Data\CepAddressInterface
     * @throws NoSuchEntityException When the CEP is invalid, unknown or the feature is off.
     */
    public function lookup(string $cep): CepAddressInterface;
}
