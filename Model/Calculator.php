<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Frenet\Command\Shipping\QuoteInterface;
use Frenet\Shipping\Api\CalculatorInterface;
use Frenet\Shipping\Api\Data\AttributesMappingInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class Calculator
 *
 * @package Frenet\Shipping\Model
 */
class Calculator implements CalculatorInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var Catalog\Product\CategoryExtractor
     */
    private $categoryExtractor;

    /**
     * @var ApiService
     */
    private $apiService;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManagement;

    /**
     * @var \Frenet\Shipping\Api\Data\ProductExtractorInterface
     */
    private $dimensionsExtractor;

    /**
     * @var \Frenet\Shipping\Api\QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var Quote\ItemQuantityCalculatorInterface
     */
    private $quoteItemQtyCalculator;

    /**
     * @var \Frenet\Shipping\Api\WeightConverterInterface
     */
    private $weightConverter;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\ProductFactory
     */
    private $productResourceFactory;

    /**
     * Calculator constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface     $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface             $storeManagement
     * @param \Magento\Catalog\Model\ResourceModel\ProductFactory    $productResourceFactory
     * @param \Magento\Checkout\Model\Session                        $checkoutSession
     * @param \Frenet\Shipping\Api\Data\DimensionsExtractorInterface $dimensionsExtractor
     * @param \Frenet\Shipping\Api\QuoteItemValidatorInterface       $quoteItemValidator
     * @param \Frenet\Shipping\Api\WeightConverterInterface          $weightConverter
     * @param Quote\ItemQuantityCalculatorInterface                  $itemQuantityCalculator
     * @param Catalog\Product\CategoryExtractor                      $categoryExtractor
     * @param CacheManager                                           $cacheManager
     * @param Config                                                 $config
     * @param ApiService                                             $apiService
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Catalog\Model\ResourceModel\ProductFactory $productResourceFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Frenet\Shipping\Api\Data\DimensionsExtractorInterface $dimensionsExtractor,
        \Frenet\Shipping\Api\QuoteItemValidatorInterface $quoteItemValidator,
        \Frenet\Shipping\Api\WeightConverterInterface $weightConverter,
        Quote\ItemQuantityCalculatorInterface $itemQuantityCalculator,
        \Frenet\Shipping\Model\Catalog\Product\CategoryExtractor $categoryExtractor,
        CacheManager $cacheManager,
        Config $config,
        ApiService $apiService
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManagement = $storeManagement;
        $this->dimensionsExtractor = $dimensionsExtractor;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->quoteItemQtyCalculator = $itemQuantityCalculator;
        $this->weightConverter = $weightConverter;
        $this->config = $config;
        $this->apiService = $apiService;
        $this->cacheManager = $cacheManager;
        $this->productResourceFactory = $productResourceFactory;
        $this->categoryExtractor = $categoryExtractor;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @inheritdoc
     */
    public function getQuote(RateRequest $request)
    {
        if ($result = $this->cacheManager->load($request)) {
            return $result;
        }

        /** @var \Frenet\Command\Shipping\QuoteInterface $quote */
        $quote = $this->apiService->shipping()->quote();
        $quote->setSellerPostcode($this->config->getOriginPostcode())
            ->setRecipientPostcode($request->getDestPostcode())
            ->setRecipientCountry($request->getCountryId())
            ->setShipmentInvoiceValue($request->getPackageValue());

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ((array) $request->getAllItems() as $item) {
            if (!$this->quoteItemValidator->validate($item)) {
                continue;
            }

            $this->addItemToQuote($quote, $item);
        }

        /**
         * Add coupon code if exists.
         */
        if ($this->getQuoteCouponCode()) {
            $quote->setCouponCode($this->getQuoteCouponCode());
        }

        /** @var \Frenet\ObjectType\Entity\Shipping\Quote $result */
        $result = $quote->execute();
        $services = $result->getShippingServices();

        if ($services) {
            $this->cacheManager->save($services, $request);
            return $services;
        }

        return false;
    }

    /**
     * @param QuoteInterface                  $quote
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return $this
     */
    private function addItemToQuote(QuoteInterface $quote, \Magento\Quote\Model\Quote\Item $item)
    {
        $this->dimensionsExtractor->setProduct($this->getProduct($item));

        $quote->addShippingItem(
            $item->getSku(),
            $this->quoteItemQtyCalculator->calculate($item),
            $this->weightConverter->convertToKg($this->dimensionsExtractor->getWeight()),
            $this->dimensionsExtractor->getLength(),
            $this->dimensionsExtractor->getHeight(),
            $this->dimensionsExtractor->getWidth(),
            $this->categoryExtractor->getProductCategories($this->getProduct($item, true)),
            $this->isProductFragile($item)
        );

        return $this;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     * @param bool                            $useParentItemIfAvailable
     *
     * @return bool|\Magento\Catalog\Model\Product
     */
    private function getProduct(\Magento\Quote\Model\Quote\Item $item, $useParentItemIfAvailable = false)
    {
        if ((true === $useParentItemIfAvailable) && $item->getParentItem()) {
            return $this->getProduct($item->getParentItem());
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $item->getProduct();
        return $product;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return bool
     */
    private function isProductFragile(\Magento\Quote\Model\Quote\Item $item)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->getProduct($item);

        if ($product->hasData(AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE)) {
            return (bool) $product->getData(AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE);
        }

        try {
            /** @var \Magento\Catalog\Model\ResourceModel\Product $resource */
            $resource = $this->productResourceFactory->create();
            $value = (bool) $resource->getAttributeRawValue(
                $product->getId(),
                AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE,
                $this->storeManagement->getStore()
            );

            return (bool) $value;
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * @return string
     */
    private function getQuoteCouponCode()
    {
        return $this->checkoutSession->getQuote()->getCouponCode();
    }
}
