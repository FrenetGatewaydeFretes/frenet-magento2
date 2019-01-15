<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Frenet\Shipping\Api\WeightConverterInterface;

/**
 * Class WeightConverter
 *
 * @package Frenet\Shipping\Model
 */
class WeightConverter implements WeightConverterInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * WeightConverter constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToKg($weight)
    {
        switch ($this->getWeightUnit()) {
            case 'lbs':
                return $weight * self::LBS_TO_KG_FACTOR;
            case 'kgs':
            default:
                return $weight;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function convertToLbs($weight)
    {
        switch ($this->getWeightUnit()) {
            case 'kgs':
                return $weight * self::KG_TO_LBS_FACTOR;
            case 'lbs':
            default:
                return $weight;
        }
    }

    /**
     * @return string|null
     */
    private function getWeightUnit()
    {
        return $this->scopeConfig->getValue('general/locale/weight_unit');
    }
}
