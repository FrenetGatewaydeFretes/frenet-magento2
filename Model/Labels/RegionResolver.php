<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Directory\Model\RegionFactory;

/**
 * Brazilian state (UF) as the 2-letter code. Some addresses keep the state name ("Rio de Janeiro")
 * instead of region_id/code; Frenet and the Correios documents need "RJ".
 */
class RegionResolver
{
    /**
     * @param RegionFactory $regionFactory
     */
    public function __construct(private readonly RegionFactory $regionFactory)
    {
    }

    /**
     * Two-letter UF from region id, code or name.
     *
     * @param int $regionId
     * @param string $code
     * @param string $name
     * @return string
     */
    public function code(int $regionId, string $code, string $name = ''): string
    {
        $code = trim($code);
        if (strlen($code) === 2) {
            return strtoupper($code);
        }
        $region = $this->regionFactory->create();
        if ($regionId) {
            $region->load($regionId);
        }
        if (!$region->getId()) {
            $region->loadByName(trim($name !== '' ? $name : $code), 'BR');
        }
        return $region->getId() ? strtoupper((string) $region->getCode()) : strtoupper($code);
    }
}
