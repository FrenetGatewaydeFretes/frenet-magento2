<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Packages;

use Frenet\Shipping\Model\Config;

/**
 * Class PackageLimit
 */
class PackageLimit
{
    /**
     * @var float
     */
    const PACKAGE_NO_LIMIT = 999999999;

    /**
     * @var float|null
     */
    private $maxWeight = null;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns the maximum weight allowed for a package.
     *
     * @return float
     */
    public function getMaxWeight()
    {
        if (null === $this->maxWeight) {
            return $this->config->getPackageMaxWeight();
        }

        return (float) $this->maxWeight;
    }

    /**
     * Overrides the package max weight for the current calculation.
     *
     * @param float $weight
     *
     * @return $this
     */
    public function setMaxWeight(float $weight)
    {
        $this->maxWeight = (float) $weight;
        return $this;
    }

    /**
     * Removes the package weight limit for the current calculation.
     *
     * @return $this
     */
    public function removeLimit()
    {
        return $this->setMaxWeight(self::PACKAGE_NO_LIMIT);
    }

    /**
     * Checks whether the package weight limit is currently disabled.
     *
     * @return bool
     */
    public function isUnlimited()
    {
        return $this->maxWeight == self::PACKAGE_NO_LIMIT;
    }

    /**
     * Restores the configured package max weight.
     *
     * @return $this
     */
    public function resetMaxWeight()
    {
        $this->maxWeight = null;
        return $this;
    }

    /**
     * Checks whether the given weight exceeds the package max weight.
     *
     * @param float $weight
     *
     * @return bool
     */
    public function isOverWeight(float $weight)
    {
        return $weight > $this->getMaxWeight();
    }
}
