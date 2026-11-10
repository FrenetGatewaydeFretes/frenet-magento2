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

/**
 * Turns the cart into one or more packages, quotes each against the Frenet API and returns a single service list.
 */
class PackagesCalculator
{
    /**
     * @var string
     */
    private const FULL_PACKAGE = 'full';

    /**
     * @param MultiQuoteValidatorInterface $multiQuoteValidator
     * @param PackageProcessor             $packageProcessor
     * @param PackageManager               $packageManager
     * @param PackageLimit                 $packageLimit
     * @param PackageMatching              $packageMatching
     */
    public function __construct(
        private readonly MultiQuoteValidatorInterface $multiQuoteValidator,
        private readonly PackageProcessor $packageProcessor,
        private readonly PackageManager $packageManager,
        private readonly PackageLimit $packageLimit,
        private readonly PackageMatching $packageMatching
    ) {
    }

    /**
     * Quotes the cart against the Frenet API, splitting it into weight-limited packages when multi-quote is on.
     *
     * @return Service[]
     */
    public function calculate(): array
    {
        $fullPackage = $this->buildFullPackage();

        if ($fullPackage === null) {
            return [];
        }

        if (!$this->shouldSplit($fullPackage)) {
            return $this->quoteFullPackage($fullPackage);
        }

        $this->packageManager->process();

        return $this->packageMatching->match($this->quotePackages());
    }

    /**
     * Packs the whole cart into one package without the weight limit, the weights every decision below is made on.
     *
     * @return Package|null
     */
    private function buildFullPackage(): ?Package
    {
        $this->packageManager->resetPackages();
        $this->packageLimit->removeLimit();

        try {
            $this->packageManager->process();
        } finally {
            $this->packageLimit->resetMaxWeight();
        }

        $this->packageManager->unsetCurrentPackage();

        return $this->packageManager->getPackages()[self::FULL_PACKAGE] ?? null;
    }

    /**
     * Checks whether the packed cart is over the weight limit and Multi Quote may split it.
     *
     * @param Package $fullPackage
     *
     * @return bool
     */
    private function shouldSplit(Package $fullPackage): bool
    {
        return $this->packageLimit->isOverWeight((float) $fullPackage->getTotalWeight())
            && $this->multiQuoteValidator->canProcessMultiQuote();
    }

    /**
     * Quotes the whole cart in a single call, keeping only the services the API could quote.
     *
     * @param Package $fullPackage
     *
     * @return Service[]
     */
    private function quoteFullPackage(Package $fullPackage): array
    {
        $services = [];

        /** @var Service $service */
        foreach ($this->packageProcessor->process($fullPackage) as $service) {
            if (!$service->isError()) {
                $services[] = $service;
            }
        }

        return $services;
    }

    /**
     * Quotes every built package, keeping each result under its package key ('full' and one entry per split package).
     *
     * @return array<int|string, Service[]>
     */
    private function quotePackages(): array
    {
        $results = [];

        /** @var Package $package */
        foreach ($this->packageManager->getPackages() as $key => $package) {
            $results[$key] = $this->packageProcessor->process($package);
        }

        return $results;
    }
}
