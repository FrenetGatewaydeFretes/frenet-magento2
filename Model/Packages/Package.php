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

    /**
     * @var WeightConverterInterface
     */
    private $weightConverter;

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
     * @param QuoteItem  $item
     * @param int        $qty
     * @param float|null $unitWeight Pre-computed unit weight (see planQuantitiesFor()); avoids re-extracting it.
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
     * @param QuoteItem  $item
     * @param int        $qty
     * @param float|null $unitWeight Pre-computed unit weight (see planQuantitiesFor()); avoids re-extracting it.
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

        /**
         * A primeira unidade do lote é comparada usando o peso cru (igual
         * ao comportamento original com $qty=1); as unidades seguintes do
         * mesmo lote somam o peso convertido, que é a mesma base usada por
         * getTotalWeight() (soma de PackageItem::getTotalWeight(), que já
         * passa por WeightConverter::convertToKg()). Sem isso, um lote com
         * $qty > 1 seria comparado de forma inconsistente com a soma que
         * getTotalWeight() reporta para o mesmo lote depois de adicionado.
         */
        $convertedWeight = (float) $this->weightConverter->convertToKg($unitWeight);
        $itemWeight = $unitWeight + $convertedWeight * ($qty - 1);

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
     * canAddItem() compara o peso *cru* do item sendo adicionado com o
     * peso *convertido para kg* já acumulado no pacote (getTotalWeight()
     * soma PackageItem::getTotalWeight(), que passa por
     * WeightConverter::convertToKg()) -- uma inconsistência pré-existente
     * que só produz efeito quando a loja não está configurada em kg
     * (general/locale/weight_unit != "kgs", que é inclusive o valor
     * padrão de fábrica do Magento_Directory). Para que o resultado do
     * empacotamento continue idêntico ao algoritmo unidade-por-unidade
     * mesmo nesse cenário, unitsFitting() replica exatamente essa mesma
     * mistura cru/convertido, em vez de assumir peso uniforme dos dois
     * lados da conta.
     *
     * Cada entrada também carrega o unitWeight já extraído aqui, para que
     * quem consome o plano não precise reextraí-lo por lote.
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
     * Quantas unidades de peso cru $rawUnitWeightScaled (com equivalente
     * convertido $convertedUnitWeightScaled) cabem numa capacidade
     * $capacityScaled, replicando a semântica de canAddItem(): a primeira
     * unidade é comparada usando peso cru; cada unidade adicional soma o
     * peso convertido (mesma base usada por getTotalWeight()).
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

        // Protege contra um WeightConverterInterface customizado (ponto de
        // extensão via DI) que devolva 0 para um peso positivo.
        return intdiv($capacityScaled - $rawUnitWeightScaled, max($convertedUnitWeightScaled, 1)) + 1;
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
