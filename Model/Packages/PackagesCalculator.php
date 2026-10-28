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

declare(strict_types=1);

namespace Frenet\Shipping\Model\Packages;

use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\Shipping\Model\Quote\MultiQuoteValidatorInterface;
use Frenet\Shipping\Model\WeightConverterInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Turns the cart into one or more packages, quotes each against the Frenet API and returns a single service list.
 */
class PackagesCalculator
{
    /**
     * @param MultiQuoteValidatorInterface $multiQuoteValidator
     * @param PackageProcessor             $packageProcessor
     * @param PackageManager               $packageManager
     * @param PackageLimit                 $packageLimit
     * @param PackageMatching              $packageMatching
     * @param RateRequestProviderInterface $rateRequestProvider
     * @param WeightConverterInterface     $weightConverter
     */
    public function __construct(
        private readonly MultiQuoteValidatorInterface $multiQuoteValidator,
        private readonly PackageProcessor $packageProcessor,
        private readonly PackageManager $packageManager,
        private readonly PackageLimit $packageLimit,
        private readonly PackageMatching $packageMatching,
        private readonly RateRequestProviderInterface $rateRequestProvider,
        private readonly WeightConverterInterface $weightConverter
    ) {
    }

    /**
     * Quotes the cart against the Frenet API, splitting it into weight-limited packages when multi-quote is on.
     *
     * @return Service[]
     */
    public function calculate(): array
    {
        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestProvider->getRateRequest();
        $this->packageManager->resetPackages();

        /**
         * If the package is not overweight then we simply process all the package.
         */
        $cartWeight = $this->weightConverter->convertToKg((float) $rateRequest->getPackageWeight());

        if (!$this->packageLimit->isOverWeight($cartWeight)) {
            return $this->processPackages();
        }

        /**
         * If the multi quote is disabled, we remove the limit.
         */
        if (!$this->multiQuoteValidator->canProcessMultiQuote()) {
            $this->packageLimit->removeLimit();
            return $this->processPackages();
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

        /**
         * Package Matching binds the results for Correios only.
         * The other options (not for Correios) are got from the full call (the first one).
         */
        return $this->packageMatching->match($this->quotePackages());
    }

    /**
     * Collapses a per-package quote into one service list, summing the price per method across packages.
     *
     * @param Service[][] $packagesServices
     *
     * @return Service[]
     */
    private function consolidatePackages(array $packagesServices): array
    {
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
     * Quotes every built package and consolidates the per-package results into a single service list.
     *
     * @return Service[]
     */
    private function processPackages(): array
    {
        return $this->consolidatePackages($this->quotePackages());
    }

    /**
     * Quotes every built package, keeping each result under its package key ('full' and one entry per split package).
     *
     * @return array<int|string, Service[]>
     */
    private function quotePackages(): array
    {
        $this->packageManager->process();
        $results = [];

        /** @var Package $package */
        foreach ($this->packageManager->getPackages() as $key => $package) {
            $results[$key] = $this->packageProcessor->process($package);
        }

        return $results;
    }
}
