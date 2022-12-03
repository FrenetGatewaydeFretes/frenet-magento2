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

namespace Frenet\Shipping\Model\Catalog\Product\View;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;
use Frenet\Shipping\Api\QuoteProductInterface;
use Frenet\Shipping\Model\Calculator;
use Frenet\Shipping\Model\Config;
use Frenet\Shipping\Service\RateRequestProvider;
use Frenet\Shipping\Model\DeliveryTimeCalculator;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;

/**
 * Class Quote
 */
class Quote implements QuoteProductInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RateRequestProvider
     */
    private $rateRequestProvider;

    /**
     * @var Calculator
     */
    private $calculator;

    /**
     * @var RateRequestBuilder
     */
    private $rateRequestBuilder;

    /**
     * @var DeliveryTimeCalculator
     */
    private $deliveryTimeCalculator;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        RateRequestProvider $rateRequestProvider,
        Calculator $calculator,
        RateRequestBuilder $rateRequestBuilder,
        LoggerInterface $logger,
        DeliveryTimeCalculator $deliveryTimeCalculator,
        Config $config
    ) {
        $this->productRepository = $productRepository;
        $this->rateRequestProvider = $rateRequestProvider;
        $this->calculator = $calculator;
        $this->rateRequestBuilder = $rateRequestBuilder;
        $this->logger = $logger;
        $this->deliveryTimeCalculator = $deliveryTimeCalculator;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function quoteByProductId(int $productId, string $postcode, int $qty = 1, array $options = []): array
    {
        try {
            $product = $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(__('Product ID %1 does not exist.', $productId));

            return [];
        }

        return $this->quote($product, $postcode, $qty, $options);
    }

    /**
     * @inheritDoc
     */
    public function quoteByProductSku(string $sku, string $postcode, int $qty = 1, array $options = []): array
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(__('Product SKU %1 does not exist.', $sku));

            return [];
        }

        return $this->quote($product, $postcode, $qty, $options);
    }

    /**
     * @inheritDoc
     */
    private function quote(ProductInterface $product, string $postcode, int $qty = 1, $options = []): array
    {
        $rateRequest = $this->rateRequestBuilder->build($product, $postcode, $qty, $options);
        $this->rateRequestProvider->setRateRequest($rateRequest);
        $services = $this->calculator->getQuote();

        return $this->prepareResult($services);
    }

    /**
     * @param ServiceInterface[] $services
     *
     * @return array
     */
    private function prepareResult(array $services): array
    {
        $result = [];

        foreach ($services as $service) {
            if (true === $service->isError()) {
                continue;
            }

            $result[] = $this->prepareService($service);
        }

        return $result;
    }

    /**
     * @param ServiceInterface $service
     *
     * @return array
     * @throws LocalizedException
     */
    private function prepareService(ServiceInterface $service): array
    {
            $deliveryTime = $this->deliveryTimeCalculator->calculate($service);
            $find = array('{{d}}');
            $replace = array($deliveryTime);
            $inputValue = array($this->config->getShippingForecastMessage());

            $forecast = str_replace($find, $replace, $inputValue);

        return [
            'service_code' => $service->getServiceCode(),
            'carrier' => $service->getCarrier(),
            'message' => $service->getMessage(),
            'delivery_time' => $deliveryTime,
            'delivery_description' => $forecast,
            'service_description' => $service->getServiceDescription(),
            'shipping_price' => $service->getShippingPrice(),
        ];
    }
}
