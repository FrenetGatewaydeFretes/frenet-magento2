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

use Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface as ShippingInfoServiceInterface;
use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface as QuoteServiceInterface;
use Frenet\ObjectType\Entity\Tracking\TrackingInfoInterface;
use Frenet\ObjectType\Entity\Tracking\TrackingInfo\EventInterface;
use Frenet\Shipping\Model\CalculatorInterface;
use Frenet\Shipping\Model\Config;
use Frenet\Shipping\Model\DeliveryTimeCalculator;
use Frenet\Shipping\Model\ServiceFinderInterface;
use Frenet\Shipping\Model\TrackingInterface;
use Frenet\Shipping\Model\Validator\PostcodeValidator;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Helper\Data as DirectoryData;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error as RateResultError;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory as RateResultErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method as MethodInstance;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result as RateResult;
use Magento\Shipping\Model\Rate\ResultFactory as RateResultFactory;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Shipping\Model\Tracking\Result as TrackingResult;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackingResultErrorFactory;
use Magento\Shipping\Model\Tracking\Result\Status;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Frenet
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Frenet extends AbstractCarrierOnline implements CarrierInterface
{
    /**
     * @var string
     */
    public const CARRIER_CODE = 'frenetshipping';

    /**
     * @var string
     */
    public const STR_SEPARATOR = ' - ';

    /**
     * @var string
     */
    protected $_code = self::CARRIER_CODE;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var RateResult|null
     */
    private $result;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RateResultErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        RateResultFactory $rateFactory,
        MethodFactory $rateMethodFactory,
        TrackingResultFactory $trackFactory,
        TrackingResultErrorFactory $trackErrorFactory,
        StatusFactory $trackStatusFactory,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        DirectoryData $directoryData,
        StockRegistryInterface $stockRegistry,
        protected readonly StoreManagerInterface $storeManagement,
        private readonly CalculatorInterface $calculator,
        private readonly TrackingInterface $trackingService,
        private readonly ServiceFinderInterface $serviceFinder,
        private readonly Config $config,
        private readonly DeliveryTimeCalculator $deliveryTimeCalculator,
        private readonly PostcodeValidator $postcodeValidator,
        private readonly RateRequestProvider $rateRequestProvider,
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
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     *
     * @return DataObject|bool|null
     * @api
     */
    public function collectRates(RateRequest $request)
    {
        try {
            if (!$this->canCollectRates()) {
                $this->_logger->debug(
                    'Frenet carrier unavailable: inactive, or missing origin postcode / API token.'
                );

                return $this->getErrorMessage();
            }

            /** This service will be used all the way long. */
            $this->rateRequestProvider->setRateRequest($request);
            $results = $this->calculator->getQuote();

            /** @var array $results */
            if (!$results) {
                $this->rateRequestProvider->clear();
                return $this->result;
            }

            $this->prepareResult($results);

            $this->rateRequestProvider->clear();

            return $this->result;
        } catch (\Throwable $e) {
            $this->rateRequestProvider->clear();
            $this->_logger->critical(
                "Error Frenet collectRates: " . $e->getMessage() . " > " . $e->getTraceAsString()
            );
        }

        return null;
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

        /** @var StoreInterface|null $store */
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
     * @param DataObject $request
     *
     * @return $this|bool|DataObject
     */
    public function proccessAdditionalValidation(DataObject $request)
    {
        return $this->processAdditionalValidation($request);
    }

    /**
     * Processing additional validation (quote data) to check if carrier applicable.
     *
     * @param RateRequest $request
     *
     * @return $this|bool|DataObject
     */
    public function processAdditionalValidation(DataObject $request)
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
            /** @var RateResultError $error */
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
     * @return TrackingResult
     * @throws LocalizedException
     */
    public function getTracking($trackingNumbers)
    {
        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [$trackingNumbers];
        }

        return $this->prepareTracking($trackingNumbers);
    }

    /**
     * @param array $trackingNumbers
     *
     * @return TrackingResult
     * @throws LocalizedException
     */
    private function prepareTracking(array $trackingNumbers)
    {
        /** @var TrackingResult $result */
        $result = $this->_trackFactory->create();

        /**
         * @var string $trackingNumber
         * @todo It's currently appending only one tracking per time. Find a solution to append more than one.
         */
        foreach ($trackingNumbers as $trackingNumber) {
            /** @var ShippingInfoServiceInterface $service */
            $service = $this->serviceFinder->findByTrackingNumber($trackingNumber);
            $serviceCode = $service ? $service->getServiceCode() : null;

            /** @var Status $status */
            $status = $this->_trackStatusFactory->create();
            $status->setCarrier(self::CARRIER_CODE);
            $status->setCarrierTitle($this->getConfigData('title'));
            $status->setTracking($trackingNumber);
            $status->setPopup(1);
            $status->setTrackSummary($this->prepareTrackingInformation($status, $trackingNumber, $serviceCode));
            $result->append($status);
        }

        return $result;
    }

    /**
     * @param Status $status
     * @param string $trackingNumber
     * @param string $shippingServiceCode
     *
     * @return void
     */
    private function prepareTrackingInformation(
        Status $status,
        $trackingNumber,
        $shippingServiceCode
    ) {
        /** @var TrackingInfoInterface $trackingInfo */
        $trackingInfo = $this->trackingService->track($trackingNumber, $shippingServiceCode);

        $events = $trackingInfo->getTrackingEvents();

        if (empty($events)) {
            return;
        }

        /** @var EventInterface $event */
        $event = end($events);

        $status->setStatus($event->getEventDescription());
        $status->setDeliveryLocation($event->getEventLocation());
        $status->setShippedDate($event->getEventDatetime());
        $status->setService($event->getTrackingInfo()->getServiceDescription());
    }

    /**
     * @inheritDoc
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _doShipmentRequest(DataObject $request)
    {
        return $this;
    }

    /**
     * Builds the rate result from the quote services returned by the Frenet API.
     *
     * @param QuoteServiceInterface[] $services
     *
     * @return $this
     */
    private function prepareResult(array $services = []) : self
    {
        /** @var RateResult $result */
        $this->result = $this->_rateFactory->create();

        /** @var QuoteServiceInterface $service */
        foreach ($services as $service) {
            if ($service->isError()) {
                continue;
            }

            $deliveryTime = $this->deliveryTimeCalculator->calculate($service);
            $serviceDescription = $service->getServiceDescription();
            if (is_array($serviceDescription)) {
                $serviceDescription = implode(" ", $service->getServiceDescription());
            }
            $serviceMessage = "".$service->getMessage();

            $title = $this->appendInformation(
                $serviceDescription,
                $deliveryTime,
                $serviceMessage
            );

            $description = $this->prepareMethodDescription(
                $service->getCarrier(),
                $serviceDescription,
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
     * Resolves the current store, or null when it cannot be determined.
     *
     * @return StoreInterface|null
     */
    private function getStore(): ?StoreInterface
    {
        try {
            return $this->storeManagement->getStore();
        } catch (NoSuchEntityException $e) {
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
     * @return Phrase|string
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
        // dias {{d}} ao inves de {{d}} aaadd
        $pattern = '/\{\{d\}\}/i';
        $replacement = "".$deliveryTime;
        $subject = $this->config->getShippingForecastMessage();
        $result = preg_replace($pattern, $replacement, $subject);
        return $result;
    }
}
