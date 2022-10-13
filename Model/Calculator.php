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
use \Psr\Log\LoggerInterface;

/**
 * Class Calculator
 */
class Calculator extends FrenetMagentoAbstract implements CalculatorInterface
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
     * @param RateRequestProvider         $rateRequestProvider
     * @param \Psr\Log\LoggerInterface    $logger
     */
    public function __construct(
        CacheManager $cacheManager,
        Packages\PackagesCalculator $packagesCalculator,
        RateRequestProvider $rateRequestProvider,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
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
        $this->_logger->debug("getQuote:cacheManager->load");
        // $this->_logger->debug(var_export($result, true));
        if ($result) {
            $this->_logger->debug("getQuote:withcache result");
            return $result;
        }

        /** @var Service[] $services */
        $services = $this->packagesCalculator->calculate();
        $this->_logger->debug("services: ".var_export($services, true));
        return [];

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
        $service->setData(
            'service_description',
            str_replace('|', "\n", $service->getServiceDescription())
        );
        return $service;
    }
}
