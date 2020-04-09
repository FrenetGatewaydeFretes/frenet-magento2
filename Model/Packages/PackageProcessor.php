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

namespace Frenet\Shipping\Model\Packages;

use Frenet\Shipping\Model\Quote\QuoteItemValidatorInterface;
use Frenet\Shipping\Model\ApiServiceInterface;
use Frenet\Shipping\Model\Config;
use Frenet\Shipping\Model\Quote\CouponProcessor;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class PackageProcessor
 *
 * @package Frenet\Shipping\Model\Packages
 */
class PackageProcessor
{
    /**
     * @var ApiServiceInterface
     */
    private $apiService;

    /**
     * @var \Frenet\Command\Shipping\QuoteInterface
     */
    private $serviceQuote;

    /**
     * @var QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CouponProcessor
     */
    private $quoteCouponProcessor;

    /**
     * @var RateRequestProvider
     */
    private $rateRequestProvider;

    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        Config $config,
        ApiServiceInterface $apiService,
        RateRequestProvider $rateRequestProvider,
        CouponProcessor $quoteCouponProcessor
    ) {
        $this->apiService = $apiService;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->config = $config;
        $this->rateRequestProvider = $rateRequestProvider;
        $this->quoteCouponProcessor = $quoteCouponProcessor;
    }

    /**
     * @param Package $package
     *
     * @return array
     */
    public function process(Package $package) : array
    {
        $this->initServiceQuote();
        $this->serviceQuote->setShipmentInvoiceValue($package->getTotalPrice());

        /** @var PackageItem $packageItem */
        foreach ($package->getItems() as $packageItem) {
            if (!$this->quoteItemValidator->validate($packageItem->getCartItem())) {
                continue;
            }

            $this->addPackageItemToQuote($packageItem);
        }

        return $this->callService();
    }

    /**
     * @param PackageItem $packageItem
     *
     * @return $this
     */
    private function addPackageItemToQuote(PackageItem $packageItem) : self
    {
        $this->serviceQuote->addShippingItem(
            $packageItem->getSku(),
            $packageItem->getQty(),
            $packageItem->getWeight(),
            $packageItem->getLength(),
            $packageItem->getHeight(),
            $packageItem->getWidth(),
            $packageItem->getProductCategories(),
            $packageItem->isProductFragile()
        );

        return $this;
    }

    /**
     * @return array
     */
    private function callService() : array
    {
        /** @var \Frenet\ObjectType\Entity\Shipping\Quote $result */
        $result = $this->serviceQuote->execute();
        $services = $result->getShippingServices();

        return $services ?: [];
    }

    /**
     * @return $this
     */
    private function initServiceQuote() : self
    {
        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestProvider->getRateRequest();

        /** @var \Frenet\Command\Shipping\QuoteInterface $quote */
        $this->serviceQuote = $this->apiService->shipping()->quote();
        $this->serviceQuote->setSellerPostcode($this->config->getOriginPostcode())
            ->setRecipientPostcode($rateRequest->getDestPostcode())
            ->setRecipientCountry($rateRequest->getCountryId());

        $this->quoteCouponProcessor->applyCouponCode($this->serviceQuote);

        return $this;
    }
}
