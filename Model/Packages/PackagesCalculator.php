<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Packages;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Frenet\Shipping\Model\Packages\PackageManager;
use Frenet\Command\Shipping\QuoteInterface;

/**
 * Class PackagesDistributor
 *
 * @package Frenet\Shipping\Model
 */
class PackagesCalculator
{
    /**
     * @var \Frenet\Command\Shipping\QuoteInterface
     */
    private $serviceQuote;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateRequest
     */
    private $rateRequest;

    /**
     * @var \Frenet\Shipping\Api\QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var PackageManager
     */
    private $packageManager;

    /**
     * @var \Frenet\Shipping\Model\ApiService
     */
    private $apiService;

    /**
     * @var \Frenet\Shipping\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var PackageLimit
     */
    private $packageLimit;

    /**
     * @var PackageMatching
     */
    private $packageMatching;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Frenet\Shipping\Api\QuoteItemValidatorInterface $quoteItemValidator,
        \Frenet\Shipping\Model\ApiService $apiService,
        \Frenet\Shipping\Model\Config $config,
        PackageManager $packagesManager,
        PackageLimit $packageLimit,
        PackageMatching $packageMatching
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->packageManager = $packagesManager;
        $this->apiService = $apiService;
        $this->config = $config;
        $this->packageLimit = $packageLimit;
        $this->packageMatching = $packageMatching;
    }

    /**
     * @param RateRequest $rateRequest
     *
     * @return RateRequest[]
     */
    public function calculate(RateRequest $rateRequest)
    {
        $this->rateRequest = $rateRequest;

        if (!$this->packageLimit->isOverWeight((float) $rateRequest->getPackageWeight())) {
            return $this->processPackages();
        }

        $this->packageLimit->removeLimit();
        $this->packageManager->process($this->rateRequest);
        $this->packageLimit->resetMaxWeight();
        $packages = $this->processPackages();

        return $this->packageMatching->match($packages);
    }

    private function processPackages()
    {
        $this->packageManager->process($this->rateRequest);
        $results = [];

        /** @var Package $package */
        foreach ($this->packageManager->getPackages() as $key => $package) {
            /** @var array $services */
            $services = $this->processPackage($package);

            /**
             * If there's only one package then we can simply return the services quote.
             */
            if ($this->packageManager->countPackages() == 1) {
                return $services;
            }

            /**
             * Otherwise we need to bind the quotes.
             */
            $results[$key] = $services;
        }

        return $results;
    }

    private function processPackage(Package $package)
    {
        $this->initServiceQuote($this->rateRequest);
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

    private function callService()
    {
        /** @var \Frenet\ObjectType\Entity\Shipping\Quote $result */
        $result = $this->serviceQuote->execute();
        $services = $result->getShippingServices();

        if ($services) {
            return $services;
        }

        return false;
    }

    private function initServiceQuote(RateRequest $rateRequest)
    {
        /** @var \Frenet\Command\Shipping\QuoteInterface $quote */
        $this->serviceQuote = $this->apiService->shipping()->quote();
        $this->serviceQuote->setSellerPostcode($this->config->getOriginPostcode())
            ->setRecipientPostcode($rateRequest->getDestPostcode())
            ->setRecipientCountry($rateRequest->getCountryId());

        /**
         * Add coupon code if exists.
         */
        if ($this->getQuoteCouponCode()) {
            $this->serviceQuote->setCouponCode($this->getQuoteCouponCode());
        }

        return $this;
    }

    /**
     * @param PackageItem $packageItem
     *
     * @return $this
     */
    private function addPackageItemToQuote(PackageItem $packageItem)
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
     * @return string
     */
    private function getQuoteCouponCode()
    {
        return $this->checkoutSession->getQuote()->getCouponCode();
    }
}
