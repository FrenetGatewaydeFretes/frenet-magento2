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
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\DeliveryTimeCalculatorInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Quotes Frenet shipping for a single product page request, returning the available services with delivery forecasts.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
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
     * @var RateRequestProviderInterface
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
     * @var DeliveryTimeCalculatorInterface
     */
    private $deliveryTimeCalculator;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param RateRequestProviderInterface $rateRequestProvider
     * @param Calculator $calculator
     * @param RateRequestBuilder $rateRequestBuilder
     * @param LoggerInterface $logger
     * @param DeliveryTimeCalculatorInterface $deliveryTimeCalculator
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        RateRequestProviderInterface $rateRequestProvider,
        Calculator $calculator,
        RateRequestBuilder $rateRequestBuilder,
        LoggerInterface $logger,
        DeliveryTimeCalculatorInterface $deliveryTimeCalculator,
        ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager
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
     * Builds the rate request for a quotable product, runs the calculator with it and clears the request afterwards.
     *
     * @param ProductInterface $product
     * @param string $postcode
     * @param int $qty
     * @param mixed[] $options
     *
     * @return mixed[][]
     */
    private function quote(ProductInterface $product, string $postcode, int $qty = 1, $options = []): array
    {
        if (!$this->isQuotable($product)) {
            return [];
        }

        $rateRequest = $this->rateRequestBuilder->build($product, $postcode, $qty, $options);
        $this->rateRequestProvider->setRateRequest($rateRequest);

        try {
            return $this->prepareResult($this->calculator->getQuote());
        } finally {
            $this->rateRequestProvider->clear();
        }
    }

    /**
     * Checks whether the product is enabled, sold in the current website and of a type allowed for the quote.
     *
     * @param ProductInterface $product
     *
     * @return bool
     */
    private function isQuotable(ProductInterface $product): bool
    {
        if (!$product instanceof Product || (int) $product->getStatus() !== Status::STATUS_ENABLED) {
            return false;
        }

        if (!$this->config->isProductQuoteAllowed((string) $product->getTypeId())) {
            return false;
        }

        $websiteId = (int) $this->storeManager->getStore()->getWebsiteId();

        return in_array($websiteId, array_map('intval', (array) $product->getWebsiteIds()), true);
    }

    /**
     * Converts the returned services into result rows, skipping the ones flagged as errors.
     *
     * @param ServiceInterface[] $services
     *
     * @return mixed[][]
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
     * Builds the result row of a service, filling the forecast message with the calculated delivery time.
     *
     * @param ServiceInterface $service
     *
     * @return mixed[]
     * @throws LocalizedException
     */
    private function prepareService(ServiceInterface $service): array
    {
        $deliveryTime = $this->deliveryTimeCalculator->calculate($service);

        $pattern = '/\{\{d\}\}/i';
        $replacement = "" . $deliveryTime;
        $subject = $this->config->getShippingForecastMessage();
        $forecast = preg_replace($pattern, $replacement, $subject);

        $serviceDescription = $service->getServiceDescription();
        if (is_array($serviceDescription)) {
            $serviceDescription = implode(" ", $serviceDescription);
        }

        return [
            'service_code' => $service->getServiceCode(),
            'carrier' => $service->getCarrier(),
            'message' => $service->getMessage(),
            'delivery_time' => $deliveryTime,
            'delivery_description' => $forecast,
            'service_description' => $serviceDescription,
            'shipping_price' => $service->getShippingPrice(),
        ];
    }
}
