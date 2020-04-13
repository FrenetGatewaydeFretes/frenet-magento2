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

use Frenet\Shipping\Api\CalculatorInterface;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class Calculator
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
     * @var RateRequestProvider
     */
    private $rateRequestProvider;

    /**
     * Calculator constructor.
     *
     * @param CacheManager                $cacheManager
     * @param Packages\PackagesCalculator $packagesCalculator
     */
    public function __construct(
        CacheManager $cacheManager,
        Packages\PackagesCalculator $packagesCalculator,
        RateRequestProvider $rateRequestProvider
    ) {
        $this->cacheManager = $cacheManager;
        $this->packagesCalculator = $packagesCalculator;
        $this->rateRequestProvider = $rateRequestProvider;
    }

    /**
     * @inheritdoc
     */
    public function getQuote()
    {
        if ($result = $this->cacheManager->load()) {
            return $result;
        }

        /** @var RateRequest[] $packages */
        $services = $this->packagesCalculator->calculate();

        if ($services) {
            $this->cacheManager->save($services);
            return $services;
        }

        return false;
    }
}
