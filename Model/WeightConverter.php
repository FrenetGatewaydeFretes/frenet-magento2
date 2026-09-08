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

namespace Frenet\Shipping\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class WeightConverter
 */
class WeightConverter implements WeightConverterInterface
{
    /**
     * @var string
     */
    private const CONFIG_PATH_WEIGHT_UNIT = 'general/locale/weight_unit';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function convertToKg(float $weight): float
    {
        if ($this->getWeightUnit() === 'lbs') {
            return $weight * self::LBS_TO_KG_FACTOR;
        }

        return $weight;
    }

    /**
     * @inheritDoc
     */
    public function convertToLbs(float $weight): float
    {
        if ($this->getWeightUnit() === 'kgs') {
            return $weight * self::KG_TO_LBS_FACTOR;
        }

        return $weight;
    }

    /**
     * Returns the weight unit configured for the store ('lbs' or 'kgs'), defaulting to 'kgs'.
     *
     * @return string
     */
    private function getWeightUnit(): string
    {
        $unit = strtolower(trim((string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_WEIGHT_UNIT,
            ScopeInterface::SCOPE_STORE
        )));

        return in_array($unit, ['lb', 'lbs', 'pound', 'pounds'], true) ? 'lbs' : 'kgs';
    }
}
