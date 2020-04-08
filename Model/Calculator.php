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
use Frenet\Shipping\Service\RateRequestService;
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
     * @var RateRequestService
     */
    private $rateRequestService;

    /**
     * Calculator constructor.
     *
     * @param CacheManager                $cacheManager
     * @param Packages\PackagesCalculator $packagesCalculator
     */
    public function __construct(
        CacheManager $cacheManager,
        Packages\PackagesCalculator $packagesCalculator,
        RateRequestService $rateRequestService
    ) {
        $this->cacheManager = $cacheManager;
        $this->packagesCalculator = $packagesCalculator;
        $this->rateRequestService = $rateRequestService;
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
