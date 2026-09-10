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

use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\Shipping\Model\Quote\MultiQuoteValidatorInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Turns the cart into one or more packages, quotes each against the Frenet API and returns a single service list.
 */
class PackagesCalculator
{
    /**
     * @var PackageManager
     */
    private $packageManager;

    /**
     * @var MultiQuoteValidatorInterface
     */
    private $multiQuoteValidator;

    /**
     * @var PackageLimit
     */
    private $packageLimit;

    /**
     * @var PackageMatching
     */
    private $packageMatching;

    /**
     * @var PackageProcessor
     */
    private $packageProcessor;

    /**
     * @var RateRequestProviderInterface
     */
    private $rateRequestProvider;

    public function __construct(
        MultiQuoteValidatorInterface $multiQuoteValidator,
        PackageProcessor $packageProcessor,
        PackageManager $packagesManager,
        PackageLimit $packageLimit,
        PackageMatching $packageMatching,
        RateRequestProviderInterface $rateRequestProvider
    ) {
        $this->packageManager = $packagesManager;
        $this->multiQuoteValidator = $multiQuoteValidator;
        $this->packageLimit = $packageLimit;
        $this->packageMatching = $packageMatching;
        $this->packageProcessor = $packageProcessor;
        $this->rateRequestProvider = $rateRequestProvider;
    }

    /**
     * @return Service[]
     */
    public function calculate()
    {
        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestProvider->getRateRequest();
        $this->packageManager->resetPackages();

        /**
         * If the package is not overweight then we simply process all the package.
         */
        if (!$this->packageLimit->isOverWeight((float) $rateRequest->getPackageWeight())) {
            return $this->consolidatePackages($this->processPackages());
        }

        /**
         * If the multi quote is disabled, we remove the limit.
         */
        if (!$this->multiQuoteValidator->canProcessMultiQuote()) {
            $this->packageLimit->removeLimit();
            return $this->consolidatePackages($this->processPackages());
        }

        /**
         * Make a full call first because of the other companies that don't have weight limit like Correios.
         */
        $this->packageLimit->removeLimit();
        $this->packageManager->process();
        $this->packageManager->unsetCurrentPackage();

        /**
         * Reset the limit so the next process will split the cart into packages.
         */
        $this->packageLimit->resetMaxWeight();
        $packages = $this->processPackages();

        /**
         * Package Matching binds the results for Correios only.
         * The other options (not for Correios) are got from the full call (the first one).
         */
        return $this->packageMatching->match($packages);
    }

    /**
     * Collapses a split-cart quote into one service list, summing the price per method across packages.
     *
     * @param array $packagesServices Service[] when the cart fit one package, Service[][] when it was split
     *
     * @return Service[]
     */
    private function consolidatePackages(array $packagesServices): array
    {
        if (!is_array(reset($packagesServices))) {
            return $packagesServices;
        }

        $packageCount = count($packagesServices);
        $totals = [];
        $counts = [];

        foreach ($packagesServices as $services) {
            /** @var Service $service */
            foreach ($services as $service) {
                if ($service->isError()) {
                    continue;
                }

                $code = (string) $service->getServiceCode();

                if (!isset($totals[$code])) {
                    $totals[$code] = $service;
                    $counts[$code] = 1;
                    continue;
                }

                $kept = $totals[$code];
                $kept->setData(
                    Service::FIELD_SHIPPING_PRICE,
                    $kept->getShippingPrice() + $service->getShippingPrice()
                );
                $kept->setData(
                    Service::FIELD_ORIGINAL_SHIPPING_PRICE,
                    $kept->getOriginalShippingPrice() + $service->getOriginalShippingPrice()
                );

                if ($service->getDeliveryTime() > $kept->getDeliveryTime()) {
                    $kept->setData(Service::FIELD_DELIVERY_TIME, $service->getDeliveryTime());
                }

                $counts[$code]++;
            }
        }

        $consolidated = [];

        foreach ($totals as $code => $service) {
            if ($counts[$code] === $packageCount) {
                $consolidated[] = $service;
            }
        }

        return $consolidated;
    }

    /**
     * @return Service[]
     */
    private function processPackages()
    {
        $this->packageManager->process();
        $results = [];

        /** @var Package $package */
        foreach ($this->packageManager->getPackages() as $key => $package) {
            /** @var Service[] $services */
            $services = $this->packageProcessor->process($package);

            /**
             * If there's only one package then we can simply return the services quote.
             */
            if ($this->packageManager->countPackages() == 1) {
                return $services;
            }

            /**
             * Otherwise we need to bind the quotes.
             */
            $results[$key] = $services;
        }

        return $results;
    }
}
