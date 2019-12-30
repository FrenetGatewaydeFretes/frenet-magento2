<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Packages;

use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class PackagesManager
 *
 * @package Frenet\Shipping\Model\Packages
 */
class PackageManager
{
    /**
     * @var Package
     */
    private $currentPackage = null;

    /**
     * @var Package[]
     */
    private $packages = [];

    /**
     * @var \Frenet\Shipping\Model\Packages\PackageFactory
     */
    private $packageFactory;

    /**
     * @var \Frenet\Shipping\Api\QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var \Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface
     */
    private $itemQuantityCalculator;

    /**
     * @var PackageLimit
     */
    private $packageLimit;

    /**
     * PackageManager constructor.
     *
     * @param \Frenet\Shipping\Api\QuoteItemValidatorInterface             $quoteItemValidator
     * @param \Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface $itemQuantityCalculator
     * @param PackageFactory                                               $packageFactory
     * @param PackageLimit                                                 $packageLimit
     */
    public function __construct(
        \Frenet\Shipping\Api\QuoteItemValidatorInterface $quoteItemValidator,
        \Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface $itemQuantityCalculator,
        PackageFactory $packageFactory,
        PackageLimit $packageLimit
    ) {
        $this->quoteItemValidator = $quoteItemValidator;
        $this->itemQuantityCalculator = $itemQuantityCalculator;
        $this->packageFactory = $packageFactory;
        $this->packageLimit = $packageLimit;
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return $this
     */
    public function process(RateRequest $rateRequest)
    {
        $this->distribute($rateRequest);
        return $this;
    }

    /**
     * @return Package[]
     */
    public function getPackages()
    {
        return $this->packages;
    }

    /**
     * @return int
     */
    public function countPackages()
    {
        return count($this->getPackages());
    }

    /**
     * @return $this
     */
    public function unsetCurrentPackage()
    {
        $this->currentPackage = null;
        return $this;
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return $this
     */
    private function distribute(RateRequest $rateRequest)
    {
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($this->getUnitItems($rateRequest) as $item) {
            $this->addItemToPackage($item);
        }

        return $this;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return bool
     */
    private function addItemToPackage(\Magento\Quote\Model\Quote\Item $item)
    {
        if (!$this->getPackage()->canAddItem($item, 1)) {
            $this->useNewPackage();
        }

        return $this->getPackage()->addItem($item, 1);
    }

    /**
     * @return Package
     */
    private function getPackage()
    {
        if (null === $this->currentPackage) {
            $this->useNewPackage();
        }

        return $this->currentPackage;
    }

    /**
     * @return $this
     */
    private function useNewPackage()
    {
        $this->currentPackage = $this->createPackage();

        if ($this->packageLimit->isUnlimited()) {
            $this->packages['full'] = $this->currentPackage;
        }

        if (!$this->packageLimit->isUnlimited()) {
            $this->packages[] = $this->currentPackage;
        }

        return $this;
    }

    /**
     * @return Package
     */
    private function createPackage()
    {
        return $this->packageFactory->create();
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return array
     */
    private function getUnitItems(RateRequest $rateRequest)
    {
        $unitItems = [];

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($rateRequest->getAllItems() as $item) {
            if (!$this->quoteItemValidator->validate($item)) {
                continue;
            }

            $qty = $this->itemQuantityCalculator->calculate($item);

            for ($i = 1; $i <= $qty; $i++) {
                $unitItems[] = $item;
            }
        }

        return $unitItems;
    }
}
