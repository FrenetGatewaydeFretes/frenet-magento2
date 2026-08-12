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
use Frenet\Shipping\Model\Quote\ItemQuantityCalculator;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Class PackageItemDistributor
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class PackageItemDistributor
{
    /**
     * @var QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var ItemQuantityCalculator
     */
    private $itemQuantityCalculator;

    /**
     * @var RateRequestProvider
     */
    private $rateRequestProvider;

    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        ItemQuantityCalculator $itemQuantityCalculator,
        RateRequestProvider $rateRequestProvider
    ) {
        $this->quoteItemValidator = $quoteItemValidator;
        $this->itemQuantityCalculator = $itemQuantityCalculator;
        $this->rateRequestProvider = $rateRequestProvider;
    }

    /**
     * @return array
     */
    public function distribute(): array
    {
        return $this->getGroupedItems();
    }

    /**
     * Retorna um par item+qty por linha válida da quote, sem explodir a
     * quantidade em cópias unitárias (ver Package::planQuantitiesFor(),
     * que faz esse trabalho por aritmética quando o pacote é montado).
     *
     * A quantidade é truncada (floor) para preservar o comportamento já
     * existente do algoritmo anterior, que descartava implicitamente a
     * parte fracionária de itens com "Qty Uses Decimals" habilitado.
     *
     * @return array{item: QuoteItem, qty: float}[]
     */
    private function getGroupedItems(): array
    {
        $rateRequest = $this->rateRequestProvider->getRateRequest();
        $groupedItems = [];

        /** @var QuoteItem $item */
        foreach ($rateRequest->getAllItems() as $item) {
            if (!$this->quoteItemValidator->validate($item)) {
                continue;
            }

            $qty = floor($this->itemQuantityCalculator->calculate($item));

            if ($qty < 1) {
                continue;
            }

            $groupedItems[] = ['item' => $item, 'qty' => $qty];
        }

        return $groupedItems;
    }
}
