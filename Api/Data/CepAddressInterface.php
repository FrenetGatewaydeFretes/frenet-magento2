<?php
/**
 * Frenet Shipping Gateway — address returned for a CEP.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Api\Data;

/**
 * @api
 */
interface CepAddressInterface
{
    /**
     * CEP formatted as 00000-000.
     *
     * @return string
     */
    public function getPostcode(): string;

    /**
     * Street (logradouro); may be empty for single-CEP cities.
     *
     * @return string
     */
    public function getStreet(): string;

    /**
     * District (bairro).
     *
     * @return string
     */
    public function getDistrict(): string;

    /**
     * City.
     *
     * @return string
     */
    public function getCity(): string;

    /**
     * State code (UF), e.g. RJ.
     *
     * @return string
     */
    public function getRegionCode(): string;

    /**
     * Magento region id of the state, 0 when unknown.
     *
     * @return int
     */
    public function getRegionId(): int;
}
