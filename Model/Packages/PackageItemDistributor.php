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
use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class PackageItemDistributor
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class PackageItemDistributor extends FrenetMagentoAbstract
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
     * @param QuoteItemValidatorInterface   $quoteItemValidator,
     * @param ItemQuantityCalculator        $itemQuantityCalculator,
     * @param RateRequestProvider           $rateRequestProvider,
     * @param \Psr\Log\LoggerInterface      $logger
     */
    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        ItemQuantityCalculator $itemQuantityCalculator,
        RateRequestProvider $rateRequestProvider,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
        $this->quoteItemValidator = $quoteItemValidator;
        $this->itemQuantityCalculator = $itemQuantityCalculator;
        $this->rateRequestProvider = $rateRequestProvider;
    }

    /**
     * @return array
     */
    public function distribute(): array
    {
        return $this->getUnitItems();
    }

    /**
     * @return array
     */
    private function getUnitItems(): array
    {
        $this->_logger->debug("packages-item-distributor:getunitItems: ");//.var_export($this->rateRequestProvider, true));
        $rateRequest = $this->rateRequestProvider->getRateRequest();
        $unitItems = [];

        /** @var QuoteItem $item */
        foreach ($rateRequest->getAllItems() as $item) {
            if (!$this->quoteItemValidator->validate($item)) {
                continue;
            }
            $qty = $this->itemQuantityCalculator->calculate($item);
            for ($idx = 1; $idx <= $qty; $idx++) {
                $unitItems[] = $item;
            }
        }
        return $unitItems;
    }
}
