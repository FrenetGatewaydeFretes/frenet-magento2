<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Frenet
 * @package Frenet\Shipping\Model\Shipping\Carrier
 */
class Frenet extends AbstractCarrierOnline implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'frenet_shipping';
    
    /**
     * @var array
     */
    private $errors = [];
    
    /**
     * @var null
     */
    private $result;
    
    /**
     * @var \Frenet\Shipping\Api\CalculatorInterface
     */
    private $calculator;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Xml\Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Frenet\Shipping\Api\CalculatorInterface $calculator,
        array $data = []
    ) {
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
        
        $this->calculator = $calculator;
    }
    
    /**
     * Make this module compatible with older versions of Magento 2.
     *
     * @param \Magento\Framework\DataObject $request
     * @return $this|bool|\Magento\Framework\DataObject
     */
    public function proccessAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        return $this->processAdditionalValidation($request);
    }
    
    /**
     * Processing additional validation (quote data) to check if carrier applicable.
     *
     * @param \Magento\Framework\DataObject $request
     *
     * @return $this|bool|\Magento\Framework\DataObject
     */
    public function processAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        /**
         * validate request items data
         */
        if (!count($this->getAllItems($request))) {
            $this->errors[] = __('There is no items in this order');
        }
        
        /**
         * validate destination postcode
         */
        if (!$request->getDestPostcode()) {
            $this->errors[] = __('Please inform the destination postcode');
        }
        
        if (!empty($this->errors)) {
            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Error $error */
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage(implode(', ', $this->errors));
            
            $this->debugErrors($error);
            
            return $error;
        }
        
        return $this;
    }
    
    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     *
     * @return \Magento\Framework\DataObject|bool|null
     * @api
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->canCollectRates()) {
            $errorMessage = $this->getErrorMessage();
            $this->_logger->debug("Frenet canCollectRates: " . $errorMessage);
            return $errorMessage;
        }
    
        /** @var \Frenet\ObjectType\Entity\Shipping\QuoteInterface $quote */
        $quote = $this->calculator->getQuote($request);
        
        if (!$quote->getShippingServices()) {
            return $this->result;
        }
    
        $this->prepareResult($quote->getShippingServices());
    
        return $this->result;
    }
    
    /**
     * Checks if shipping method is correctly configured
     *
     * @return bool
     */
    public function canCollectRates()
    {
        /**
         * validate carrier active flag
         */
        if (!$this->getConfigFlag($this->_activeFlag)) {
            return false;
        }
        
        /** @var int $store */
        $store = $this->getStore();
        
        /**
         * validate origin postcode
         */
        if (!$this->_scopeConfig->getValue('shipping/origin/postcode', ScopeInterface::SCOPE_STORE, $store)) {
            return false;
        }
        
        /**
         * validate frenet token
         */
        if (!$this->getConfigData('token')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get allowed shipping methods
     *
     * @return array
     * @api
     */
    public function getAllowedMethods()
    {
        // TODO: Implement getAllowedMethods() method.
    }
    
    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param \Magento\Framework\DataObject $request
     *
     * @return \Magento\Framework\DataObject
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        // TODO: Implement _doShipmentRequest() method.
    }
    
    
    private function prepareResult(array $items = [])
    {
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $this->result = $this->_rateFactory->create();
    
        /** @var \Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface $item */
        foreach ($items as $item) {
            if ($item->isError()) {
                continue;
            }
            
            $title  = $this->prepareMethodTitle($item->getCarrier(), $item->getServiceDescription());
            $method = $this->prepareMethod($title, $title, $item->getShippingPrice(), $item->getShippingPrice());
            
            $this->result->append($method);
        }
    
        return $this;
    }
    
    /**
     * @param $method
     * @param $title
     * @param $price
     * @param $cost
     *
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     */
    private function prepareMethod($method, $title, $price, $cost)
    {
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $methodInstance */
        $methodInstance = $this->_rateMethodFactory->create();
        $methodInstance->setCarrier($this->_code)
            ->setCarrierTitle($this->getConfigData('title'))
            ->setMethod($method)
            ->setMethodTitle($title)
            ->setPrice($price)
            ->setCost($cost);
        
        return $methodInstance;
    }
    
    /**
     * @param string $carrier
     * @param string $description
     *
     * @return string
     */
    private function prepareMethodTitle($carrier, $description)
    {
        return "{$carrier} - {$description}";
    }
}
