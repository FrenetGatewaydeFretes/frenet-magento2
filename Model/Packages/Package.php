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
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Class Package
 */
class Package
{
    /**
     * Precision (decimal places) used when scaling weights to integers for
     * planQuantitiesFor(), matching PackageLimit::PACKAGE_MAX_WEIGHT's precision.
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
    public function getRemainingWeight(): float
    {
        return $this->packageLimit->getMaxWeight() - $this->getTotalWeight();
    }

    /**
     * Plano de como distribuir $requestedQty unidades de $item entre pacotes,
     * calculado inteiramente por aritmética (divisão/módulo inteiros, sem
     * while/for dependente da quantidade). Cada entrada indica quantas
     * unidades entram num pacote e se é necessário abrir um pacote novo
     * antes de adicioná-las.
     *
     * O primeiro lote (newPackage => false) só entra no plano quando cabe
     * algo no pacote atual (que pode já estar parcialmente ocupado por um
     * item anterior); os lotes seguintes (newPackage => true) assumem
     * sempre um pacote novo e vazio, com capacidade cheia.
     *
     * @param QuoteItem $item
     * @param float     $requestedQty
     *
     * @return array{newPackage: bool, qty: float}[]
     */
    public function planQuantitiesFor(QuoteItem $item, float $requestedQty): array
    {
        $this->dimensionsExtractor->setProductByCartItem($item);
        $unitWeight = (float) $this->dimensionsExtractor->getWeight();

        if ($unitWeight <= 0) {
            return [['newPackage' => false, 'qty' => $requestedQty]];
        }

        $unitWeightScaled = (int) round($unitWeight * self::WEIGHT_SCALE);
        $fullCapacityScaled = (int) round($this->packageLimit->getMaxWeight() * self::WEIGHT_SCALE);
        $remainingScaled = max(0, (int) round($this->getRemainingWeight() * self::WEIGHT_SCALE));

        $unitsPerFullPackage = intdiv($fullCapacityScaled, $unitWeightScaled);

        if ($unitsPerFullPackage < 1) {
            /**
             * Peso unitário sozinho excede o limite mesmo de um pacote
             * vazio. Comportamento herdado: o algoritmo unidade-por-unidade
             * anterior também descartava esse item silenciosamente (via
             * canAddItem() retornando false). Não introduzido por este
             * método.
             */
            return [];
        }

        $requestedQtyInt = (int) $requestedQty;
        $firstBatch = min($requestedQtyInt, intdiv($remainingScaled, $unitWeightScaled));
        $remaining = $requestedQtyInt - $firstBatch;

        $plan = [];

        if ($firstBatch > 0) {
            $plan[] = ['newPackage' => false, 'qty' => (float) $firstBatch];
        }

        $fullPackagesCount = intdiv($remaining, $unitsPerFullPackage);
        $leftover = $remaining % $unitsPerFullPackage;

        if ($fullPackagesCount > 0) {
            $plan = array_merge(
                $plan,
                array_fill(0, $fullPackagesCount, ['newPackage' => true, 'qty' => (float) $unitsPerFullPackage])
            );
        }

        if ($leftover > 0) {
            $plan[] = ['newPackage' => true, 'qty' => (float) $leftover];
        }

        return $plan;
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
