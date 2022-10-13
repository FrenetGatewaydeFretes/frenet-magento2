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
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class PackagesDistributor
 */
class PackagesCalculator extends FrenetMagentoAbstract
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
     * @var RateRequestProvider
     */
    private $rateRequestProvider;


    /**
     * @param MultiQuoteValidatorInterface $multiQuoteValidator
     * @param PackageProcessor $packageProcessor
     * @param PackageManager $packagesManager
     * @param PackageLimit $packageLimit
     * @param PackageMatching $packageMatching
     * @param RateRequestProvider $rateRequestProvider
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        MultiQuoteValidatorInterface $multiQuoteValidator,
        PackageProcessor $packageProcessor,
        PackageManager $packagesManager,
        PackageLimit $packageLimit,
        PackageMatching $packageMatching,
        RateRequestProvider $rateRequestProvider,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
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
        $this->_logger->debug("packages-calculator:pre-calculate: ");//.var_export($this->rateRequestProvider, true));
        $rateRequest = $this->rateRequestProvider->getRateRequest();
        $this->_logger->debug("calculate: ".var_export($rateRequest, true));
        return [];

        $this->packageManager->resetPackages();

        /**
         * If the package is not overweight then we simply process all the package.
         */
        if (!$this->packageLimit->isOverWeight((float) $rateRequest->getPackageWeight())) {
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
        $packages = $this->processPackages();

        /**
         * Package Matching binds the results for Correios only.
         * The other options (not for Correios) are got from the full call (the first one).
         */
        return $this->packageMatching->match($packages);
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
