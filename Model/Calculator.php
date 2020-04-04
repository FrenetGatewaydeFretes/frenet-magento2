<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Frenet\Command\Shipping\QuoteInterface;
use Frenet\Shipping\Api\CalculatorInterface;
use Frenet\Shipping\Api\Data\AttributesMappingInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class Calculator
 *
 * @package Frenet\Shipping\Model
 */
class Calculator implements CalculatorInterface
{
    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @var Packages\PackagesCalculator
     */
    private $packagesCalculator;

    /**
     * Calculator constructor.
     *
     * @param CacheManager                $cacheManager
     * @param Packages\PackagesCalculator $packagesCalculator
     */
    public function __construct(
        CacheManager $cacheManager,
        Packages\PackagesCalculator $packagesCalculator
    ) {
        $this->cacheManager = $cacheManager;
        $this->packagesCalculator = $packagesCalculator;
    }

    /**
     * @inheritdoc
     */
    public function getQuote(RateRequest $request)
    {
        if ($result = $this->cacheManager->load($request)) {
            return $result;
        }

        /** @var RateRequest[] $packages */
        $services = $this->packagesCalculator->calculate($request);

        if ($services) {
            $this->cacheManager->save($services, $request);
            return $services;
        }

        return false;
    }
}
