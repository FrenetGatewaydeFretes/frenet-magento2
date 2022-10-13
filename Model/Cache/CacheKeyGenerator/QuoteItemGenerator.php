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

namespace Frenet\Shipping\Model\Cache\CacheKeyGenerator;

use Frenet\Shipping\Model\Cache\CacheKeyGeneratorInterface;
use Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface;
use Frenet\Shipping\Model\Quote\QuoteItemValidatorInterface;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

class QuoteItemGenerator extends FrenetMagentoAbstract implements CacheKeyGeneratorInterface
{
    /**
     * @var RateRequestProvider
     */
    private $requestProvider;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var ItemQuantityCalculatorInterface
     */
    private $itemQtyCalculator;

    /**
     * @param SerializerInterface               $serializer
     * @param RateRequestProvider               $requestProvider
     * @param QuoteItemValidatorInterface       $quoteItemValidator
     * @param ItemQuantityCalculatorInterface   $itemQtyCalculator
     * @param \Psr\Log\LoggerInterface          $logger
     */
    public function __construct(
        SerializerInterface $serializer,
        RateRequestProvider $requestProvider,
        QuoteItemValidatorInterface $quoteItemValidator,
        ItemQuantityCalculatorInterface $itemQtyCalculator,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
        $this->serializer = $serializer;
        $this->requestProvider = $requestProvider;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->itemQtyCalculator = $itemQtyCalculator;
    }

    /**
     * @inheritDoc
     */
    public function generate()
    {
        $items = [];

        $this->_logger->debug("quoteitem-generator:pre-calculate: ");//.var_export($this->rateRequestProvider, true));
        /** @var QuoteItem $item */
        foreach ($this->requestProvider->getRateRequest()->getAllItems() as $item) {
            if (!$this->quoteItemValidator->validate($item)) {
                continue;
            }

            $productId = (int) $item->getProductId();

            if ($item->getParentItem()) {
                $productId = $item->getParentItem()->getProductId() . '-' . $productId;
            }

            $qty = (float) $this->itemQtyCalculator->calculate($item);

            $items[$productId] = $qty;
        }

        ksort($items);

        return $this->serializer->serialize($items);
    }
}
