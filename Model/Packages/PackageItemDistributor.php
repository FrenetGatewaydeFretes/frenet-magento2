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

    /**
     * @param QuoteItemValidatorInterface $quoteItemValidator
     * @param ItemQuantityCalculator      $itemQuantityCalculator
     * @param RateRequestProvider         $rateRequestProvider
     */
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
     * Returns the quote items grouped with their quantity, ready to be packed.
     *
     * @return array
     */
    public function distribute(): array
    {
        return $this->getGroupedItems();
    }

    /**
     * Returns one item+qty pair per valid quote line, without exploding the quantity into unit copies.
     *
     * Package::planQuantitiesFor() does that split arithmetically when the
     * package is built. The quantity is truncated (floor) to preserve the
     * previous algorithm's behavior, which implicitly discarded the
     * fractional part of items with "Qty Uses Decimals" enabled.
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
