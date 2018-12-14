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
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManagement;
    
    /**
     * Calculator constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManagement
     * @param ApiService                                         $apiService
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        ApiService $apiService
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManagement = $storeManagement;
        $this->apiService = $apiService;
    }
    
    /**
     * @inheritdoc
     */
    public function getQuote(RateRequest $request)
    {
        /** @var  $quote */
        $quote = $this->apiService->shipping()->quote();
        $quote->setSellerPostcode($this->getOriginPostcode())
            ->setRecipientPostcode($request->getDestPostcode())
            ->setRecipientCountry($request->getCountryId())
            ->setShipmentInvoiceValue($request->getPackageValue());
        
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ((array) $request->getAllItems() as $item) {
            $quote->addShippingItem($item->getSku(), $item->getQty(), $item->getWeight(), 1, 1, 1, 'test');
        }
        
        return $quote->execute();
    }
    
    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getOriginPostcode()
    {
        return $this->scopeConfig->getValue(
            'shipping/origin/postcode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManagement->getStore()->getId()
        );
    }
}
