<?php

namespace Frenet\Shipping\Model\Packages;

use Frenet\Shipping\Model\Quote\CouponProcessor;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class PackageProcessor
 *
 * @package Frenet\Shipping\Model\Packages
 */
class PackageProcessor
{
    /**
     * @var \Frenet\Shipping\Model\ApiService
     */
    private $apiService;

    /**
     * @var \Frenet\Command\Shipping\QuoteInterface
     */
    private $serviceQuote;

    /**
     * @var \Frenet\Shipping\Api\QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Frenet\Shipping\Model\Config
     */
    private $config;

    /**
     * @var CouponProcessor
     */
    private $quoteCouponProcessor;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Frenet\Shipping\Api\QuoteItemValidatorInterface $quoteItemValidator,
        \Frenet\Shipping\Model\Config $config,
        \Frenet\Shipping\Model\ApiService $apiService,
        CouponProcessor $quoteCouponProcessor
    ) {
        $this->apiService = $apiService;
        $this->checkoutSession = $checkoutSession;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->config = $config;
        $this->quoteCouponProcessor = $quoteCouponProcessor;
    }

    /**
     * @param Package     $package
     * @param RateRequest $rateRequest
     *
     * @return array
     */
    public function process(Package $package, RateRequest $rateRequest) : array
    {
        $this->initServiceQuote($rateRequest);
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
     * @param RateRequest $rateRequest
     *
     * @return $this
     */
    private function initServiceQuote(RateRequest $rateRequest) : self
    {
        /** @var \Frenet\Command\Shipping\QuoteInterface $quote */
        $this->serviceQuote = $this->apiService->shipping()->quote();
        $this->serviceQuote->setSellerPostcode($this->config->getOriginPostcode())
            ->setRecipientPostcode($rateRequest->getDestPostcode())
            ->setRecipientCountry($rateRequest->getCountryId());

        $this->quoteCouponProcessor->applyCouponCode($this->serviceQuote);

        return $this;
    }
}
