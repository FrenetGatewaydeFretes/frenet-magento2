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

namespace Frenet\Shipping\Model\Packages;

use Frenet\Shipping\Model\Quote\MultiQuoteValidatorInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class PackagesDistributor
 *
 * @package Frenet\Shipping\Model
 */
class PackagesCalculator
{
    /**
     * @var \Magento\Quote\Model\Quote\Address\RateRequest
     */
    private $rateRequest;

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

    public function __construct(
        MultiQuoteValidatorInterface $multiQuoteValidator,
        PackageProcessor $packageProcessor,
        PackageManager $packagesManager,
        PackageLimit $packageLimit,
        PackageMatching $packageMatching
    ) {
        $this->packageManager = $packagesManager;
        $this->multiQuoteValidator = $multiQuoteValidator;
        $this->packageLimit = $packageLimit;
        $this->packageMatching = $packageMatching;
        $this->packageProcessor = $packageProcessor;
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return RateRequest[]
     */
    public function calculate(RateRequest $rateRequest)
    {
        $this->rateRequest = $rateRequest;

        /**
         * If the package is not overweight then we simply process all the package.
         */
        if (!$this->packageLimit->isOverWeight((float) $rateRequest->getPackageWeight())) {
            return $this->processPackages();
        }

        /**
         * If the multi quote is disabled, we remove the limit.
         */
        if (!$this->multiQuoteValidator->canProcessMultiQuote($rateRequest)) {
            $this->packageLimit->removeLimit();
            return $this->processPackages();
        }

        /**
         * Make a full call first because of the other companies that don't have weight limit like Correios.
         */
        $this->packageLimit->removeLimit();
        $this->packageManager->process($this->rateRequest);
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
     * @return array
     */
    private function processPackages()
    {
        $this->packageManager->process($this->rateRequest);
        $results = [];

        /** @var Package $package */
        foreach ($this->packageManager->getPackages() as $key => $package) {
            /** @var array $services */
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
