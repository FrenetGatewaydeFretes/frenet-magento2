<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Packages;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item as QuoteItem;

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
     * @var PackageItemDistributor
     */
    private $packageItemDistributor;

    /**
     * PackageManager constructor.
     *
     * @param \Frenet\Shipping\Api\QuoteItemValidatorInterface             $quoteItemValidator
     * @param \Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface $itemQuantityCalculator
     * @param PackageFactory                                               $packageFactory
     * @param PackageLimit                                                 $packageLimit
     * @param PackageItemDistributor                                       $packageItemDistributor
     */
    public function __construct(
        \Frenet\Shipping\Api\QuoteItemValidatorInterface $quoteItemValidator,
        \Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface $itemQuantityCalculator,
        PackageFactory $packageFactory,
        PackageLimit $packageLimit,
        PackageItemDistributor $packageItemDistributor
    ) {
        $this->quoteItemValidator = $quoteItemValidator;
        $this->itemQuantityCalculator = $itemQuantityCalculator;
        $this->packageFactory = $packageFactory;
        $this->packageLimit = $packageLimit;
        $this->packageItemDistributor = $packageItemDistributor;
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return $this
     */
    public function process(RateRequest $rateRequest)
    {
        $items = $this->packageItemDistributor->distribute($rateRequest);

        /** @var QuoteItem $item */
        foreach ($items as $item) {
            $this->addItemToPackage($item);
        }

        return $this;
    }

    /**
     * @return Package[]
     */
    public function getPackages() : array
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
     * @param QuoteItem $item
     *
     * @return bool
     */
    private function addItemToPackage(QuoteItem $item)
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
}
