<?php
declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Frenet\Command\Shipping\QuoteInterface;
use Frenet\Shipping\Api\CalculatorInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class Calculator
 *
 * @package Frenet\Shipping\Model
 */
class Calculator implements CalculatorInterface
{
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
     * @var \Frenet\Shipping\Api\QuoteItemValidator
     */
    private $quoteItemValidator;
    
    /**
     * Calculator constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface     $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface             $storeManagement
     * @param \Frenet\Shipping\Api\Data\DimensionsExtractorInterface $dimensionsExtractor
     * @param ApiService                                             $apiService
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Frenet\Shipping\Api\Data\DimensionsExtractorInterface $dimensionsExtractor,
        \Frenet\Shipping\Api\QuoteItemValidator $quoteItemValidator,
        CacheManager $cacheManager,
        Config $config,
        ApiService $apiService
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManagement = $storeManagement;
        $this->dimensionsExtractor = $dimensionsExtractor;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->config = $config;
        $this->apiService = $apiService;
        $this->cacheManager = $cacheManager;
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
            ->setShipmentInvoiceValue($request->getPackageValue())
        ;
        
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ((array) $request->getAllItems() as $item) {
            if (!$this->quoteItemValidator->validate($item)) {
                continue;
            }
            
            $this->addItemToQuote($quote, $item);
        }
        
        /** @var \Frenet\ObjectType\Entity\Shipping\Quote $result */
        $result   = $quote->execute();
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
     *
     * @return $this
     */
    private function addItemToQuote(QuoteInterface $quote, \Magento\Quote\Model\Quote\Item $item)
    {
        /**
         * The right quantity for configurable products are on the parent item.
         */
        $qty = $item->getParentItemId() ? $item->getParentItem()->getQty() : $item->getQty();
        
        $this->dimensionsExtractor->setProduct($this->getProduct($item));
        $quote->addShippingItem(
            $item->getSku(),
            $qty,
            $this->dimensionsExtractor->getWeight(),
            $this->dimensionsExtractor->getLength(),
            $this->dimensionsExtractor->getHeight(),
            $this->dimensionsExtractor->getWidth(),
            $this->getProductCategory($item)
        );
        
        return $this;
    }
    
    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return bool|\Magento\Catalog\Model\Product
     */
    private function getProduct(\Magento\Quote\Model\Quote\Item $item)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $item->getProduct();
        return $product;
    }
    
    /**
     * @param \Magento\Catalog\Model\Product $product
     */
    private function getProductCategory(\Magento\Quote\Model\Quote\Item $item)
    {
        if ($item->getParentItemId()) {
            return $this->getProductCategory($item->getParentItem());
        }
        
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->getProduct($item)->getCategoryCollection();
        $collection->addAttributeToSelect('name');
        
        /** @var \Magento\Catalog\Model\Category $category */
        $category = $collection->getFirstItem();
        
        if ($category) {
            return $category->getName();
        }
        
        return null;
    }
}
