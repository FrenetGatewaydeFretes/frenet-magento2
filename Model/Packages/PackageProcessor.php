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
use Frenet\Shipping\Model\Config;
use Frenet\Shipping\Model\Quote\CouponProcessor;
use Frenet\Shipping\Model\TotalsCollector;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class PackageProcessor
 */
class PackageProcessor extends FrenetMagentoAbstract
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

    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    /**
     * @param QuoteItemValidatorInterface   $quoteItemValidator
     * @param Config                        $config
     * @param ApiServiceInterface           $apiService
     * @param RateRequestProvider           $rateRequestProvider
     * @param CouponProcessor               $quoteCouponProcessor
     * @param TotalsCollector               $totalsCollector
     * @param \Psr\Log\LoggerInterface      $logger
     */
    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        Config $config,
        ApiServiceInterface $apiService,
        RateRequestProvider $rateRequestProvider,
        CouponProcessor $quoteCouponProcessor,
        TotalsCollector $totalsCollector,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
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
        $totalPrice = $package->getTotalPrice();
        $totalPrice += $this->totalsCollector->calculateQuoteAdditions();
        $totalPrice -= $this->totalsCollector->calculateQuoteDiscounts();
        $this->serviceQuote->setShipmentInvoiceValue($totalPrice);
        return $this;
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
        $this->_logger->debug("packages-processor:pre-initServiceQuote: ");//.var_export($this->rateRequestProvider, true));
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
