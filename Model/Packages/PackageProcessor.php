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

namespace Frenet\Shipping\Model\Packages;

use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\Shipping\Model\Quote\QuoteItemValidatorInterface;
use Frenet\Shipping\Model\ApiServiceInterface;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Quote\CouponProcessor;
use Frenet\Shipping\Model\TotalsCollector;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class PackageProcessor
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
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var CouponProcessor
     */
    private $quoteCouponProcessor;

    /**
     * @var RateRequestProviderInterface
     */
    private $rateRequestProvider;

    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        ConfigInterface $config,
        ApiServiceInterface $apiService,
        RateRequestProviderInterface $rateRequestProvider,
        CouponProcessor $quoteCouponProcessor,
        TotalsCollector $totalsCollector
    ) {
        $this->apiService = $apiService;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->config = $config;
        $this->rateRequestProvider = $rateRequestProvider;
        $this->quoteCouponProcessor = $quoteCouponProcessor;
        $this->totalsCollector = $totalsCollector;
    }

    /**
     * @param Package $package
     *
     * @return Service[]
     */
    public function process(Package $package) : array
    {
        $this->initServiceQuote();
        $this->calculateShipmentInvoiceValue($package);

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
     * @param Package $package
     *
     * @return $this
     */
    private function calculateShipmentInvoiceValue(Package $package)
    {
        $quote = $this->getPackageQuote($package);
        $totalPrice = $package->getTotalPrice();

        /**
         * Skip the totals collector entirely when there is no quote to give it - never pass null
         * through. TotalsCollector::getQuote() falls back to the checkout session for a null quote
         * once a discount/addition collector is configured, reopening the exact re-entrancy hazard
         * fixed here (magento/magento2#34830). A package built by PackageManager always carries an
         * item with a quote, so this only guards a theoretical edge case.
         */
        if ($quote) {
            $totalPrice += $this->totalsCollector->calculateQuoteAdditions($quote);
            $totalPrice -= $this->totalsCollector->calculateQuoteDiscounts($quote);
        }

        $this->serviceQuote->setShipmentInvoiceValue($totalPrice);
        return $this;
    }

    /**
     * Reads the quote off the package's own items so the totals collector never falls back to the session.
     *
     * @param Package $package
     *
     * @return Quote|null
     */
    private function getPackageQuote(Package $package): ?Quote
    {
        foreach ($package->getItems() as $packageItem) {
            $quote = $packageItem->getCartItem()->getQuote();
            if ($quote instanceof Quote) {
                return $quote;
            }
        }
        return null;
    }

    /**
     * @param PackageItem $packageItem
     *
     * @return $this
     */
    private function addPackageItemToQuote(PackageItem $packageItem): self
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
     * @return Service[]
     */
    private function callService(): array
    {
        /** @var \Frenet\ObjectType\Entity\Shipping\Quote $result */
        $result = $this->serviceQuote->execute();
        $services = $result->getShippingServices();

        return $services ?: [];
    }

    /**
     * @return $this
     */
    private function initServiceQuote(): self
    {
        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestProvider->getRateRequest();

        /** @var \Frenet\Command\Shipping\QuoteInterface $quote */
        $this->serviceQuote = $this->apiService->shipping()->quote();
        $this->serviceQuote->setSellerPostcode($this->config->getOriginPostcode())
            ->setRecipientPostcode($rateRequest->getDestPostcode())
            ->setRecipientCountry($rateRequest->getDestCountryId());

        $this->quoteCouponProcessor->applyCouponCode($this->serviceQuote);

        return $this;
    }
}
