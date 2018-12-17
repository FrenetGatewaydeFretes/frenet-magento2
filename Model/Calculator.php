<?php
declare(strict_types = 1);

namespace Frenet\Shipping\Model;

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
        Config $config,
        ApiService $apiService
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManagement = $storeManagement;
        $this->dimensionsExtractor = $dimensionsExtractor;
        $this->config = $config;
        $this->apiService = $apiService;
    }
    
    /**
     * @inheritdoc
     */
    public function getQuote(RateRequest $request)
    {
        /** @var  $quote */
        $quote = $this->apiService->shipping()->quote();
        $quote->setSellerPostcode($this->config->getOriginPostcode())
            ->setRecipientPostcode($request->getDestPostcode())
            ->setRecipientCountry($request->getCountryId())
            ->setShipmentInvoiceValue($request->getPackageValue())
        ;
        
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ((array) $request->getAllItems() as $item) {
            $this->dimensionsExtractor->setProduct($item->getProduct());
            $quote->addShippingItem(
                $item->getSku(),
                $item->getQty(),
                $this->dimensionsExtractor->getWeight(),
                $this->dimensionsExtractor->getLength(),
                $this->dimensionsExtractor->getHeight(),
                $this->dimensionsExtractor->getWeight(),
                'test'
            );
        }
        
        return $quote->execute();
    }
}
