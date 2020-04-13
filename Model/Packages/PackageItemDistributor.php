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

use Frenet\Shipping\Api\QuoteItemValidatorInterface;
use Frenet\Shipping\Model\Quote\ItemQuantityCalculator;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class PackageItemDistributor
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

    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        ItemQuantityCalculator $itemQuantityCalculator
    ) {
        $this->quoteItemValidator = $quoteItemValidator;
        $this->itemQuantityCalculator = $itemQuantityCalculator;
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return array
     */
    public function distribute(RateRequest $rateRequest) : array
    {
        return (array) $this->getUnitItems($rateRequest);
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return array
     */
    private function getUnitItems(RateRequest $rateRequest) : array
    {
        $unitItems = [];

        /** @var QuoteItem $item */
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
