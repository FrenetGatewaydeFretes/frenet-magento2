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

use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class Package
 *
 * @package Frenet\Shipping\Model\Packages
 */
class Package
{
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

    public function __construct(
        DimensionsExtractorInterface $dimensionsExtractor,
        PackageItemFactory $packageItemFactory,
        PackageLimit $packageLimit
    ) {
        $this->dimensionsExtractor = $dimensionsExtractor;
        $this->packageItemFactory = $packageItemFactory;
        $this->packageLimit = $packageLimit;
    }

    /**
     * @param QuoteItem $item
     * @param int       $qty
     *
     * @return bool
     */
    public function addItem(QuoteItem $item, $qty = 1)
    {
        if (!$this->canAddItem($item, $qty)) {
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
     * @return PackageItem[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param $itemId
     *
     * @return PackageItem|null
     */
    public function getItemById($itemId)
    {
        return isset($this->items[$itemId]) ? $this->items[$itemId] : null;
    }

    /**
     * @param QuoteItem $item
     * @param int       $qty
     *
     * @return bool
     */
    public function canAddItem(QuoteItem $item, $qty = 1)
    {
        $this->dimensionsExtractor->setProductByCartItem($item);

        $weight = $this->dimensionsExtractor->getWeight();
        $itemWeight = $weight * $qty;

        if (($itemWeight + $this->getTotalWeight()) > $this->packageLimit->getMaxWeight()) {
            return false;
        }

        return true;
    }

    /**
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
     * @param QuoteItem $item
     *
     * @return bool
     */
    private function itemExists(QuoteItem $item)
    {
        return isset($this->items[$item->getId()]);
    }

    /**
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
