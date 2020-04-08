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

use Frenet\Shipping\Api\QuoteItemValidatorInterface;
use Frenet\Shipping\Model\Quote\ItemQuantityCalculator;
use Frenet\Shipping\Service\RateRequestService;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class PackageItemDistributor
 *
 * @package Frenet\Shipping\Model\Packages
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
     * @var RateRequestService
     */
    private $rateRequestService;

    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        ItemQuantityCalculator $itemQuantityCalculator,
        RateRequestService $rateRequestService
    ) {
        $this->quoteItemValidator = $quoteItemValidator;
        $this->itemQuantityCalculator = $itemQuantityCalculator;
        $this->rateRequestService = $rateRequestService;
    }

    /**
     * @return array
     */
    public function distribute() : array
    {
        return (array) $this->getUnitItems();
    }

    /**
     * @return array
     */
    private function getUnitItems() : array
    {
        $rateRequest = $this->rateRequestService->getRateRequest();
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
