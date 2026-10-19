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

use Frenet\Command\Shipping\QuoteInterface;
use Frenet\DataObjectInterface;
use Frenet\ObjectType\Entity\Shipping\Quote as ShippingQuoteResult;
use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\Shipping\Model\ApiServiceInterface;
use Frenet\Shipping\Model\CacheManager;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Quote\CouponProcessor;
use Frenet\Shipping\Model\Quote\QuoteItemValidatorInterface;
use Frenet\Shipping\Model\TotalsCollector;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Quotes one package against the Frenet API, reusing the answer for an identical request from memory or the cache.
 */
class PackageProcessor implements ResetAfterRequestInterface
{
    /**
     * @var ApiServiceInterface
     */
    private $apiService;

    /**
     * @var QuoteInterface
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

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @var array<string, Service[]>
     */
    private array $responses = [];

    public function __construct(
        QuoteItemValidatorInterface $quoteItemValidator,
        ConfigInterface $config,
        ApiServiceInterface $apiService,
        RateRequestProviderInterface $rateRequestProvider,
        CouponProcessor $quoteCouponProcessor,
        TotalsCollector $totalsCollector,
        CacheManager $cacheManager
    ) {
        $this->apiService = $apiService;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->config = $config;
        $this->rateRequestProvider = $rateRequestProvider;
        $this->quoteCouponProcessor = $quoteCouponProcessor;
        $this->totalsCollector = $totalsCollector;
        $this->cacheManager = $cacheManager;
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
        $requestKey = $this->getRequestKey();

        if ($requestKey === null) {
            return $this->executeQuote();
        }

        if (!array_key_exists($requestKey, $this->responses)) {
            $this->responses[$requestKey] = $this->cacheManager->load($requestKey)
                ?? $this->executeAndStore($requestKey);
        }

        return array_map(fn (Service $service) => clone $service, $this->responses[$requestKey]);
    }

    /**
     * Hashes the account, the API host and the full payload of the quote, or returns null when it is unreadable.
     *
     * @return string|null
     */
    private function getRequestKey(): ?string
    {
        if (!$this->serviceQuote instanceof DataObjectInterface) {
            return null;
        }

        $request = json_encode([
            $this->config->getToken(),
            $this->config->getApiHostname(),
            $this->serviceQuote->getData(),
        ]);

        return $request === false ? null : hash('sha256', $request);
    }

    /**
     * Sends the quote to the Frenet API and keeps a non-empty answer in the cache.
     *
     * @param string $requestKey
     *
     * @return Service[]
     */
    private function executeAndStore(string $requestKey): array
    {
        $services = $this->executeQuote();
        $this->cacheManager->save($requestKey, $services);

        return $services;
    }

    /**
     * Sends the quote to the Frenet API.
     *
     * @return Service[]
     */
    private function executeQuote(): array
    {
        /** @var ShippingQuoteResult $result */
        $result = $this->serviceQuote->execute();

        return $result->getShippingServices() ?: [];
    }

    /**
     * @inheritDoc
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _resetState(): void
    {
        $this->responses = [];
    }

    /**
     * @return $this
     */
    private function initServiceQuote(): self
    {
        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestProvider->getRateRequest();

        $this->serviceQuote = $this->apiService->shipping()->quote();
        $this->serviceQuote->setSellerPostcode($this->config->getOriginPostcode())
            ->setRecipientPostcode($rateRequest->getDestPostcode())
            ->setRecipientCountry($rateRequest->getDestCountryId());

        $this->quoteCouponProcessor->applyCouponCode($this->serviceQuote);

        return $this;
    }
}
