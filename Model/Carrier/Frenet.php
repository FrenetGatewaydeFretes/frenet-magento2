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

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method as MethodInstance;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface as QuoteServiceInterface;

/**
 * Class Frenet
 */
class Frenet extends AbstractCarrierOnline implements CarrierInterface
{
    /**
     * @var string
     */
    const CARRIER_CODE = 'frenetshipping';

    /**
     * @var string
     */
    const STR_SEPARATOR = ' - ';

    /**
     * @var string
     */
    protected $_code = self::CARRIER_CODE;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagement;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\ProductFactory
     */
    private $productResourceFactory;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var null
     */
    private $result;

    /**
     * @var \Frenet\Shipping\Model\CalculatorInterface
     */
    private $calculator;

    /**
     * @var \Frenet\Shipping\Model\DeliveryTimeCalculator
     */
    private $deliveryTimeCalculator;

    /**
     * @var \Frenet\Shipping\Model\TrackingInterface
     */
    private $trackingService;

    /**
     * @var \Frenet\Shipping\Model\ServiceFinderInterface
     */
    private $serviceFinder;

    /**
     * @var \Frenet\Shipping\Model\Formatters\PostcodeNormalizer
     */
    private $postcodeNormalizer;

    /**
     * @var \Frenet\Shipping\Model\Config
     */
    private $config;

    /**
     * @var \Frenet\Shipping\Model\Validator\PostcodeValidator
     */
    private $postcodeValidator;

    /**
     * @var \Frenet\Shipping\Service\RateRequestProvider
     */
    private $rateRequestProvider;

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
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Catalog\Model\ResourceModel\ProductFactory $productResourceFactory,
        \Frenet\Shipping\Model\CalculatorInterface $calculator,
        \Frenet\Shipping\Model\TrackingInterface $trackingService,
        \Frenet\Shipping\Model\ServiceFinderInterface $serviceFinder,
        \Frenet\Shipping\Model\Config $config,
        \Frenet\Shipping\Model\DeliveryTimeCalculator $deliveryTimeCalculator,
        \Frenet\Shipping\Model\Formatters\PostcodeNormalizer $postcodeNormalizer,
        \Frenet\Shipping\Model\Validator\PostcodeValidator $postcodeValidator,
        \Frenet\Shipping\Service\RateRequestProvider $rateRequestProvider,
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

        $this->storeManagement = $storeManagement;
        $this->productResourceFactory = $productResourceFactory;
        $this->trackingService = $trackingService;
        $this->calculator = $calculator;
        $this->serviceFinder = $serviceFinder;
        $this->config = $config;
        $this->deliveryTimeCalculator = $deliveryTimeCalculator;
        $this->postcodeNormalizer = $postcodeNormalizer;
        $this->postcodeValidator = $postcodeValidator;
        $this->rateRequestProvider = $rateRequestProvider;
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

        /** This service will be used all the way long. */
        $this->rateRequestProvider->setRateRequest($request);

        /** @var array $results */
        if (!$results = $this->calculator->getQuote()) {
            $this->rateRequestProvider->clear();
            return $this->result;
        }

        $this->prepareResult($results);

        $this->rateRequestProvider->clear();

        return $this->result;
    }

    /**
     * Checks if shipping method is correctly configured
     *
     * @return bool
     */
    public function canCollectRates()
    {
        /** Validate carrier active flag */
        if (!$this->config->isActive()) {
            return false;
        }

        /** @var int $store */
        $store = $this->getStore();

        /** Validate origin postcode */
        if (!$this->config->getOriginPostcode($store)) {
            return false;
        }

        /** Validate frenet token */
        if (!$this->config->getToken()) {
            return false;
        }

        return true;
    }

    /**
     * Make this module compatible with older versions of Magento 2.
     *
     * @param \Magento\Framework\DataObject $request
     *
     * @return $this|bool|\Magento\Framework\DataObject
     */
    public function proccessAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        return $this->processAdditionalValidation($request);
    }

    /**
     * Processing additional validation (quote data) to check if carrier applicable.
     *
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     *
     * @return $this|bool|\Magento\Framework\DataObject
     */
    public function processAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        /** Validate destination postcode */
        if (!$this->postcodeValidator->validate($request->getDestPostcode())) {
            $this->errors[] = __('Please inform a valid postcode');
        }

        /** Validate request items data */
        if (empty($request->getAllItems())) {
            $this->errors[] = __('There is no items in this order');
        }

        if (!empty($this->errors)) {
            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Error $error */
            $error = $this->_rateErrorFactory->create([
                'carrier'       => $this->_code,
                'carrier_title' => $this->config->getCarrierConfig('title'),
                'error_message' => implode(', ', $this->errors)
            ]);

            $this->debugErrors($error);

            return $error;
        }

        return $this;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     * @api
     */
    public function getAllowedMethods()
    {
        return [self::CARRIER_CODE => $this->config->getCarrierConfig('name')];
    }

    /**
     * @param $trackingNumbers
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTracking($trackingNumbers)
    {
        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [$trackingNumbers];
        }

        $this->prepareTracking($trackingNumbers);

        return $this->result;
    }

    /**
     * @param array $trackingNumbers
     *
     * @return \Magento\Shipping\Model\Tracking\Result
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function prepareTracking(array $trackingNumbers)
    {
        /** @var \Magento\Shipping\Model\Tracking\Result $result */
        $result = $this->_trackFactory->create();

        /** @var string $trackingNumber */
        foreach ($trackingNumbers as $trackingNumber) {
            /** @var \Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface $service */
            $service = $this->serviceFinder->findByTrackingNumber($trackingNumber);
            $serviceCode = $service ? $service->getServiceCode() : null;

            /** @var \Magento\Shipping\Model\Tracking\Result\Status $status */
            $status = $this->_trackStatusFactory->create();
            $status->setCarrier(self::CARRIER_CODE);
            $status->setCarrierTitle($this->getConfigData('title'));
            $status->setTracking($trackingNumber);
            $status->setPopup(1);
            $status->setTrackSummary($this->prepareTrackingInformation($status, $trackingNumber, $serviceCode));
            $result->append($status);
        }

        $this->result = $result;

        return $result;
    }

    /**
     * @param \Magento\Shipping\Model\Tracking\Result\Status $status
     * @param string                                         $trackingNumber
     * @param string                                         $shippingServiceCode
     *
     * @return void
     */
    private function prepareTrackingInformation($status, $trackingNumber, $shippingServiceCode)
    {
        /** @var \Frenet\ObjectType\Entity\Tracking\TrackingInfoInterface $trackingInfo */
        $trackingInfo = $this->trackingService->track($trackingNumber, $shippingServiceCode);

        $events = $trackingInfo->getTrackingEvents();

        if (empty($events)) {
            return;
        }

        /** @var \Frenet\ObjectType\Entity\Tracking\TrackingInfo\EventInterface $event */
        $event = end($events);

        $status->setStatus($event->getEventDescription());
        $status->setDeliveryLocation($event->getEventLocation());
        $status->setShippedDate($event->getEventDatetime());
        $status->setService($event->getTrackingInfo()->getServiceDescription());
    }

    /**
     * @inheritdoc
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        return $this;
    }

    /**
     * @param RateRequest             $request
     * @param QuoteServiceInterface[] $items
     *
     * @return $this
     */
    private function prepareResult(array $services = []) : self
    {
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $this->result = $this->_rateFactory->create();

        /** @var QuoteServiceInterface $service */
        foreach ($services as $service) {
            if ($service->isError()) {
                continue;
            }

            $deliveryTime = $this->deliveryTimeCalculator->calculate($service);

            $title = $this->appendInformation(
                $service->getServiceDescription(),
                $deliveryTime,
                $service->getMessage()
            );

            $description = $this->prepareMethodDescription(
                $service->getCarrier(),
                $service->getServiceDescription(),
                $deliveryTime
            );

            $method = $this->prepareMethod(
                $service->getServiceCode(),
                $title,
                $description,
                (float) $service->getShippingPrice(),
                (float) $service->getShippingPrice()
            );

            $this->result->append($method);
        }

        return $this;
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface
     */
    private function getStore()
    {
        try {
            return $this->storeManagement->getStore();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @param string $code
     * @param string $title
     * @param string $description
     * @param float  $price
     * @param float  $cost
     *
     * @return MethodInstance
     */
    private function prepareMethod(
        string $code,
        string $title,
        string $description,
        float $price,
        float $cost
    ) : MethodInstance {
        /** @var MethodInstance $methodInstance */
        $methodInstance = $this->_rateMethodFactory->create();
        $methodInstance->setCarrier($this->_code)
            ->setCarrierTitle($this->config->getCarrierConfig('title'))
            ->setMethod($code)
            ->setMethodTitle($title)
            ->setMethodDescription($description)
            ->setPrice($price)
            ->setCost($cost);

        return $methodInstance;
    }

    /**
     * @param string $carrier
     * @param string $description
     * @param int    $deliveryTime
     *
     * @return \Magento\Framework\Phrase|string
     */
    private function prepareMethodDescription(string $carrier, string $description, $deliveryTime = 0)
    {
        $title = __('%1' . self::STR_SEPARATOR . '%2', $carrier, $description);
        $title = $this->appendInformation($title, $deliveryTime);

        return $title;
    }

    /**
     * @param string $text
     * @param int    $deliveryTime
     * @param string $message
     *
     * @return string
     */
    private function appendInformation($text, $deliveryTime = 0, $message = null)
    {
        if ($this->config->canShowShippingForecast()) {
            $text .= self::STR_SEPARATOR . $this->getDeliveryTimeMessage($deliveryTime);
        }

        /**
         * In some cases the API returns some messages about restrictions or extended delivery time.
         * This is where this information will be displayed.
         */
        if ($message) {
            $text .= " ({$message})";
        }

        return $text;
    }

    /**
     * @param int $deliveryTime
     *
     * @return mixed
     */
    private function getDeliveryTimeMessage($deliveryTime = 0)
    {
        return str_replace('{{d}}', (int) $deliveryTime, $this->config->getShippingForecastMessage());
    }
}
