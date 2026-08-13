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

use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\WeightConverterInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Class Package
 */
class Package
{
    /**
     * Scale factor used to convert weights to integers for exact packing arithmetic.
     */
    private const WEIGHT_SCALE = 10000;

    /**
     * @var array
     */
    private $items = [];

    /**
     * @var PackageLimit
     */
    private $packageLimit;

    /**
     * @var DimensionsExtractorInterface
     */
    private $dimensionsExtractor;

    /**
     * @var PackageItemFactory
     */
    private $packageItemFactory;

    /**
     * @var WeightConverterInterface
     */
    private $weightConverter;

    /**
     * @param DimensionsExtractorInterface $dimensionsExtractor
     * @param PackageItemFactory           $packageItemFactory
     * @param PackageLimit                 $packageLimit
     * @param WeightConverterInterface     $weightConverter
     */
    public function __construct(
        DimensionsExtractorInterface $dimensionsExtractor,
        PackageItemFactory $packageItemFactory,
        PackageLimit $packageLimit,
        WeightConverterInterface $weightConverter
    ) {
        $this->dimensionsExtractor = $dimensionsExtractor;
        $this->packageItemFactory = $packageItemFactory;
        $this->packageLimit = $packageLimit;
        $this->weightConverter = $weightConverter;
    }

    /**
     * Adds a quantity of an item to the package.
     *
     * @param QuoteItem  $item
     * @param int        $qty
     * @param float|null $unitWeight
     *
     * @return bool
     */
    public function addItem(QuoteItem $item, $qty = 1, ?float $unitWeight = null)
    {
        if (!$this->canAddItem($item, $qty, $unitWeight)) {
            return false;
        }

        /** @var PackageItem $packageItem */
        $packageItem = $this->getItemById($item->getId()) ?: $this->packageItemFactory->create([
            'cartItem' => $item
        ]);

        $packageItem->setQty($this->getItemQty($item) + $qty);

        $this->items[$item->getId()] = $packageItem;

        return true;
    }

    /**
     * Returns the items currently in the package.
     *
     * @return PackageItem[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Returns a package item by its cart item ID.
     *
     * @param $itemId
     *
     * @return PackageItem|null
     */
    public function getItemById($itemId)
    {
        return isset($this->items[$itemId]) ? $this->items[$itemId] : null;
    }

    /**
     * Checks whether a quantity of an item still fits within the package weight limit.
     *
     * @param QuoteItem  $item
     * @param int        $qty
     * @param float|null $unitWeight
     *
     * @return bool
     */
    public function canAddItem(QuoteItem $item, $qty = 1, ?float $unitWeight = null)
    {
        if ($qty <= 0) {
            return true;
        }

        if ($unitWeight === null) {
            $this->dimensionsExtractor->setProductByCartItem($item);
            $unitWeight = (float) $this->dimensionsExtractor->getWeight();
        }

        $convertedWeight = (float) $this->weightConverter->convertToKg($unitWeight);
        $itemWeight = $unitWeight + $convertedWeight * ($qty - 1);

        if (($itemWeight + $this->getTotalWeight()) > $this->packageLimit->getMaxWeight()) {
            return false;
        }

        return true;
    }

    /**
     * Returns the total weight of all items in the package.
     *
     * @return float
     */
    public function getTotalWeight()
    {
        $total = 0.0000;

        /** @var PackageItem $packageItem */
        foreach ($this->getItems() as $packageItem) {
            $total += $packageItem->getTotalWeight();
        }

        return (float) $total;
    }

    /**
     * Returns the weight capacity still available in the package.
     *
     * @return float
     */
    public function getRemainingWeight(): float
    {
        return $this->packageLimit->getMaxWeight() - $this->getTotalWeight();
    }

    /**
     * Plans how to distribute a quantity of an item across packages.
     *
     * @param QuoteItem $item
     * @param float     $requestedQty
     *
     * @return array{newPackage: bool, qty: float, unitWeight: float}[]
     */
    public function planQuantitiesFor(QuoteItem $item, float $requestedQty): array
    {
        $this->dimensionsExtractor->setProductByCartItem($item);
        $unitWeight = (float) $this->dimensionsExtractor->getWeight();

        if ($unitWeight <= 0) {
            return [['newPackage' => false, 'qty' => $requestedQty, 'unitWeight' => $unitWeight]];
        }

        $convertedUnitWeight = (float) $this->weightConverter->convertToKg($unitWeight);

        $unitWeightScaled = (int) round($unitWeight * self::WEIGHT_SCALE);
        $convertedUnitWeightScaled = (int) round($convertedUnitWeight * self::WEIGHT_SCALE);
        $fullCapacityScaled = (int) round($this->packageLimit->getMaxWeight() * self::WEIGHT_SCALE);
        $remainingScaled = max(0, (int) round($this->getRemainingWeight() * self::WEIGHT_SCALE));

        $unitsPerFullPackage = $this->unitsFitting($fullCapacityScaled, $unitWeightScaled, $convertedUnitWeightScaled);

        if ($unitsPerFullPackage < 1) {
            return [];
        }

        $requestedQtyInt = (int) $requestedQty;
        $firstBatch = min(
            $requestedQtyInt,
            $this->unitsFitting($remainingScaled, $unitWeightScaled, $convertedUnitWeightScaled)
        );
        $remaining = $requestedQtyInt - $firstBatch;

        $plan = [];

        if ($firstBatch > 0) {
            $plan[] = ['newPackage' => false, 'qty' => (float) $firstBatch, 'unitWeight' => $unitWeight];
        }

        $fullPackagesCount = intdiv($remaining, $unitsPerFullPackage);
        $leftover = $remaining % $unitsPerFullPackage;

        if ($fullPackagesCount > 0) {
            $plan = array_merge(
                $plan,
                array_fill(
                    0,
                    $fullPackagesCount,
                    ['newPackage' => true, 'qty' => (float) $unitsPerFullPackage, 'unitWeight' => $unitWeight]
                )
            );
        }

        if ($leftover > 0) {
            $plan[] = ['newPackage' => true, 'qty' => (float) $leftover, 'unitWeight' => $unitWeight];
        }

        return $plan;
    }

    /**
     * Computes how many units fit in a given weight capacity.
     *
     * @param int $capacityScaled
     * @param int $rawUnitWeightScaled
     * @param int $convertedUnitWeightScaled
     *
     * @return int
     */
    private function unitsFitting(int $capacityScaled, int $rawUnitWeightScaled, int $convertedUnitWeightScaled): int
    {
        if ($rawUnitWeightScaled > $capacityScaled) {
            return 0;
        }

        return intdiv($capacityScaled - $rawUnitWeightScaled, max($convertedUnitWeightScaled, 1)) + 1;
    }

    /**
     * Returns the total price of all items in the package.
     *
     * @return float
     */
    public function getTotalPrice()
    {
        $total = 0.0000;

        /** @var PackageItem $packageItem */
        foreach ($this->getItems() as $packageItem) {
            $total += $packageItem->getTotalPrice();
        }

        return $total;
    }

    /**
     * Checks whether the given item is already in the package.
     *
     * @param QuoteItem $item
     *
     * @return bool
     */
    private function itemExists(QuoteItem $item)
    {
        return isset($this->items[$item->getId()]);
    }

    /**
     * Returns the quantity already added for the given item.
     *
     * @param QuoteItem $item
     *
     * @return float
     */
    private function getItemQty(QuoteItem $item)
    {
        if ($this->itemExists($item)) {
            return (float) $this->getItemById($item->getId())->getQty();
        }

        return 0.0000;
    }
}
