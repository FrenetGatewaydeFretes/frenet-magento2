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
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

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
     * Distributes the quote items into packages.
     *
     * @return $this
     */
    public function process(): self
    {
        foreach ($this->packageItemDistributor->distribute() as $entry) {
            $this->addItemToPackage($entry['item'], $entry['qty']);
        }

        return $this;
    }

    /**
     * Returns the packages built so far.
     *
     * @return Package[]
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * Returns the number of packages built so far.
     *
     * @return int
     */
    public function countPackages()
    {
        return count($this->getPackages());
    }

    /**
     * Clears the current package reference.
     *
     * @return $this
     */
    public function unsetCurrentPackage()
    {
        $this->currentPackage = null;
        return $this;
    }

    /**
     * Clears all built packages.
     *
     * @return $this
     */
    public function resetPackages() : self
    {
        $this->packages = [];
        $this->unsetCurrentPackage();
        return $this;
    }

    /**
     * Creates a new, empty package.
     *
     * @return Package
     */
    public function createPackage()
    {
        return $this->packageFactory->create();
    }

    /**
     * Applies a quote item's packing plan, opening new packages as needed.
     *
     * @param QuoteItem $item
     * @param float     $qty
     *
     * @return void
     */
    private function addItemToPackage(QuoteItem $item, float $qty): void
    {
        $plan = $this->getPackage()->planQuantitiesFor($item, $qty);

        foreach ($plan as $batch) {
            if ($batch['newPackage']) {
                $this->useNewPackage();
            }
            $this->getPackage()->addItem($item, $batch['qty'], $batch['unitWeight']);
        }
    }

    /**
     * Returns the current package, creating one if none exists yet.
     *
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
     * Starts a new current package and registers it.
     *
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
