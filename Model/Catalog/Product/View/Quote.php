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
use Frenet\Shipping\Model\Packages\Package;
use Frenet\Shipping\Model\Packages\PackageProcessor;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class Quote
 *
 * @package Frenet\Shipping\Model\Catalog\Product\View
 */
class Quote implements QuoteProductInterface
{
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Frenet\Shipping\Model\Packages\PackageManager
     */
    private $packageManager;

    /**
     * @var PackageProcessor
     */
    private $packageProcessor;

    /**
     * @var QuoteItemConvertor
     */
    private $quoteItemConvertor;

    /**
     * @var RateRequestProvider
     */
    private $rateRequestProvider;

    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Frenet\Shipping\Model\Packages\PackageManager $packageManager,
        \Frenet\Shipping\Model\Packages\PackageProcessor $packageProcessor,
        QuoteItemConvertor $quoteItemConvertor,
        RateRequestProvider $rateRequestProvider,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->packageManager = $packageManager;
        $this->packageProcessor = $packageProcessor;
        $this->quoteItemConvertor = $quoteItemConvertor;
        $this->rateRequestProvider = $rateRequestProvider;
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
        /** @var QuoteItem $item */
        $item = $this->quoteItemConvertor->convert($product);

        $rateRequest = $this->rateRequestProvider->createRateRequest();
        $rateRequest->setDestPostcode($postcode);
        $rateRequest->setDestCountryId('BR');
        $this->rateRequestProvider->setRateRequest($rateRequest);

        /** @var Package $package */
        $package = $this->packageManager->createPackage();
        $package->addItem($item, $qty);

        $services = $this->packageProcessor->process($package);

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
