<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sums the carrier forecast, the slowest item's lead time and the store's extra days into one delivery estimate.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class DeliveryTimeCalculator implements DeliveryTimeCalculatorInterface
{
    public function __construct(
        private readonly ProductResource $productResource,
        private readonly StoreManagerInterface $storeManagement,
        private readonly ConfigInterface $config,
        private readonly RateRequestProviderInterface $rateRequestProvider
    ) {
    }

    /**
     * @inheritDoc
     */
    public function calculate(ServiceInterface $service): int
    {
        $rateRequest = $this->rateRequestProvider->getRateRequest();
        $serviceForecast = (int) $service->getDeliveryTime();
        $maxProductForecast = 0;

        /** @var QuoteItem $item */
        foreach ($rateRequest->getAllItems() as $item) {
            $leadTime = $this->extractProductLeadTime($item->getProduct());

            if ($maxProductForecast >= $leadTime) {
                continue;
            }

            $maxProductForecast = $leadTime;
        }

        return $serviceForecast + $maxProductForecast + $this->config->getAdditionalLeadTime();
    }

    /**
     * Reads the product's lead time, falling back to the raw store-scoped attribute value when it is empty.
     *
     * @param Product $product
     *
     * @return int
     * @throws NoSuchEntityException
     */
    private function extractProductLeadTime(Product $product): int
    {
        $leadTime = max((int) $product->getData('lead_time'), 0);

        if (!$leadTime) {
            $leadTime = $this->productResource->getAttributeRawValue(
                $product->getId(),
                'lead_time',
                $this->storeManagement->getStore()
            );
        }

        return (int) $leadTime;
    }
}
