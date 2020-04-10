<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */
declare(strict_types = 1);

namespace Frenet\Shipping\Model\Catalog\Product\View;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;
use Frenet\Shipping\Api\QuoteProductInterface;
use Frenet\Shipping\Model\Calculator;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;

/**
 * Class Quote
 *
 * @package Frenet\Shipping\Model\Catalog\Product\View
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

    public function __construct(
        ProductRepositoryInterface $productRepository,
        RateRequestProvider $rateRequestProvider,
        Calculator $calculator,
        RateRequestBuilder $rateRequestBuilder,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->rateRequestProvider = $rateRequestProvider;
        $this->calculator = $calculator;
        $this->rateRequestBuilder = $rateRequestBuilder;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function quoteByProductId(int $id, string $postcode, int $qty = 1) : array
    {
        try {
            $product = $this->productRepository->getById($id);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(__('Product ID %1 does not exist.', $id));

            return [];
        }

        return $this->quote($product, $postcode, $qty);
    }

    /**
     * @inheritDoc
     */
    public function quoteByProductSku(string $sku, string $postcode, int $qty = 1) : array
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(__('Product SKU %1 does not exist.', $sku));

            return [];
        }

        return $this->quote($product, $postcode, $qty);
    }

    /**
     * @inheritDoc
     */
    private function quote(ProductInterface $product, string $postcode, int $qty = 1) : array
    {
        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestBuilder->build($product, $postcode, $qty);
        $this->rateRequestProvider->setRateRequest($rateRequest);
        $services = $this->calculator->getQuote();

        return $this->prepareResult($services);
    }

    /**
     * @param ServiceInterface[] $services
     *
     * @return array
     */
    private function prepareResult(array $services) : array
    {
        $result = [];

        /** @var ServiceInterface $service */
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
     */
    private function prepareService(ServiceInterface $service) : array
    {
        return [
            'service_code'        => $service->getServiceCode(),
            'carrier'             => $service->getCarrier(),
            'message'             => $service->getMessage(),
            'delivery_time'       => $service->getDeliveryTime(),
            'service_description' => $service->getServiceDescription(),
            'shipping_price'      => $service->getShippingPrice(),
        ];
    }
}
