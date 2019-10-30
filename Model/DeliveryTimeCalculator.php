<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;

/**
 * Class DeliveryTimeCalculator
 *
 * @package Frenet\Shipping\Model
 */
class DeliveryTimeCalculator
{
    /**
     * @var \Frenet\Shipping\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\ProductFactory
     */
    private $productResourceFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * DeliveryTimeCalculator constructor.
     *
     * @param \Magento\Catalog\Model\ResourceModel\ProductFactory $productResourceFactory
     * @param \Magento\Store\Model\StoreManagerInterface          $storeManager
     * @param Config                                              $config
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\ProductFactory $productResourceFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Frenet\Shipping\Model\Config $config
    ) {
        $this->productResourceFactory = $productResourceFactory;
        $this->storeManager = $storeManager;
        $this->config = $config;
    }

    /**
     * @param RateRequest      $rateRequest
     * @param ServiceInterface $service
     *
     * @return int
     */
    public function calculate(RateRequest $rateRequest, ServiceInterface $service)
    {
        $serviceForecast = $service->getDeliveryTime();
        $maxProductForecast = 0;

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($rateRequest->getAllItems() as $item) {
            $leadTime = $this->extractProductLeadTime($item->getProduct());

            if ($maxProductForecast >= $leadTime) {
                continue;
            }

            $maxProductForecast = $leadTime;
        }

        return ($serviceForecast + $maxProductForecast + $this->config->getAdditionalLeadTime());
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return int
     */
    private function extractProductLeadTime(\Magento\Catalog\Model\Product $product)
    {
        $leadTime = max($product->getData('lead_time'), 0);

        if (!$leadTime) {
            $leadTime = $this->productResourceFactory
                ->create()
                ->getAttributeRawValue($product->getId(), 'lead_time', $this->storeManager->getStore());
        }

        return (int) $leadTime;
    }
}
