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

/**
 * Quotes the cart through the package calculator and turns the service descriptions into display text.
 */
class Calculator implements CalculatorInterface
{
    public function __construct(
        private readonly Packages\PackagesCalculator $packagesCalculator
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getQuote(): array
    {
        $services = $this->packagesCalculator->calculate();

        foreach ($services as $service) {
            $this->processService($service);
        }

        return $services;
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
