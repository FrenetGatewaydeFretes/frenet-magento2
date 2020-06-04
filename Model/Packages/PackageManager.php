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

use Frenet\Shipping\Model\Quote\QuoteItemValidatorInterface;
use Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class PackagesManager
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class PackageManager
{
    /**
     * @var Package
     */
    private $currentPackage;

    /**
     * @var Package[]
     */
    private $packages = [];

    /**
     * @var \Frenet\Shipping\Model\Packages\PackageFactory
     */
    private $packageFactory;

    /**
     * @var QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var ItemQuantityCalculatorInterface
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
     * @param QuoteItemValidatorInterface     $quoteItemValidator
     * @param ItemQuantityCalculatorInterface $itemQuantityCalculator
     * @param PackageFactory                  $packageFactory
     * @param PackageLimit                    $packageLimit
     * @param PackageItemDistributor          $packageItemDistributor
     */
    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        ItemQuantityCalculatorInterface $itemQuantityCalculator,
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
     * @return $this
     */
    public function process() : self
    {
        $items = $this->packageItemDistributor->distribute();

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
     * @return Package
     */
    public function createPackage()
    {
        return $this->packageFactory->create();
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
}
