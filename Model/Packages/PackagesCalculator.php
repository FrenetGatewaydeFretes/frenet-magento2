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
     * @var \Frenet\Shipping\Model\Quote\MultiQuoteValidatorInterface
     */
    private $multiQuoteValidator;

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
        \Frenet\Shipping\Model\Quote\MultiQuoteValidatorInterface $multiQuoteValidator,
        PackageManager $packagesManager,
        PackageLimit $packageLimit,
        PackageMatching $packageMatching
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->packageManager = $packagesManager;
        $this->apiService = $apiService;
        $this->config = $config;
        $this->multiQuoteValidator = $multiQuoteValidator;
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

        /**
         * If the multi quote is disabled, we remove the limit.
         */
        if (!$this->multiQuoteValidator->canProcessMultiQuote($rateRequest)) {
            $this->packageLimit->removeLimit();
            return $this->processPackages();
        }

        /**
         * Make a full call first because of the other companies that don't have weight limit like Correios.
         */
        $this->packageLimit->removeLimit();
        $this->packageManager->process($this->rateRequest);
        $this->packageManager->unsetCurrentPackage();

        /**
         * Reset the limit so the next process will split the cart into packages.
         */
        $this->packageLimit->resetMaxWeight();
        $packages = $this->processPackages();

        /**
         * Package Matching binds the results for Correios only.
         * The other options (not for Correios) are got from the full call (the first one).
         */
        return $this->packageMatching->match($packages);
    }

    /**
     * @return array
     */
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

    /**
     * @param Package $package
     *
     * @return array|bool
     */
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

    /**
     * @return array|bool
     */
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

    /**
     * @param RateRequest $rateRequest
     *
     * @return $this
     */
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
