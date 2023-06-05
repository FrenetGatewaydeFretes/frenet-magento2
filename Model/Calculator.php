<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model;

use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\Shipping\Service\RateRequestProvider;

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
    public function getQuote(): array
    {
        $result = $this->cacheManager->load();
        if ($result) {
            return $result;
        }

        /** @var Service[] $services */
        $services = $this->packagesCalculator->calculate();

        foreach ($services as $service) {
            $this->processService($service);
        }

        if ($services) {
            $this->cacheManager->save($services);
            return $services;
        }

        return [];
    }

    /**
     * @param Service $service
     *
     * @return Service
     */
    private function processService(Service $service): Service
    {
        $serviceDescription = $service->getServiceDescription();
        if (is_array($serviceDescription)) {
            $serviceDescription = implode(" ", $serviceDescription);
        }
        $find = ['|'];
        $replace = ["\n"];
        $inputValue = [$serviceDescription];
        $result = str_replace($find, $replace, $inputValue);

        $service->setData('service_description', $result);
        return $service;
    }
}
