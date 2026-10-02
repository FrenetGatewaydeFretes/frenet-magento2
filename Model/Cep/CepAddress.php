<?php
/**
 * Frenet Shipping Gateway — address of a CEP (value object).
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Cep;

use Frenet\Shipping\Api\Data\CepAddressInterface;

class CepAddress implements CepAddressInterface
{
    /**
     * @param string $postcode
     * @param string $street
     * @param string $district
     * @param string $city
     * @param string $regionCode
     * @param int $regionId
     */
    public function __construct(
        private readonly string $postcode = '',
        private readonly string $street = '',
        private readonly string $district = '',
        private readonly string $city = '',
        private readonly string $regionCode = '',
        private readonly int $regionId = 0
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getPostcode(): string
    {
        return $this->postcode;
    }

    /**
     * @inheritdoc
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * @inheritdoc
     */
    public function getDistrict(): string
    {
        return $this->district;
    }

    /**
     * @inheritdoc
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @inheritdoc
     */
    public function getRegionCode(): string
    {
        return $this->regionCode;
    }

    /**
     * @inheritdoc
     */
    public function getRegionId(): int
    {
        return $this->regionId;
    }
}
