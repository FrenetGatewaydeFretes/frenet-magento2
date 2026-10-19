<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model;

use Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface;
use Frenet\ObjectType\Entity\Shipping\InfoInterface;
use Frenet\Shipping\Model\Carrier\Frenet as FrenetCarrier;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\Track;

/**
 * Finds the Frenet shipping service behind a tracking number by matching it against the order's shipping description.
 */
class ServiceFinder implements ServiceFinderInterface
{
    /**
     * @var ApiServiceInterface
     */
    private $apiService;

    /**
     * @var ShipmentTrackRepositoryInterface
     */
    private $trackRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @param ApiServiceInterface              $apiService
     * @param ShipmentTrackRepositoryInterface $trackRepository
     * @param SearchCriteriaBuilder            $criteriaBuilder
     */
    public function __construct(
        ApiServiceInterface $apiService,
        ShipmentTrackRepositoryInterface $trackRepository,
        SearchCriteriaBuilder $criteriaBuilder
    ) {
        $this->apiService = $apiService;
        $this->trackRepository = $trackRepository;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @inheritDoc
     */
    public function findByTrackingNumber($trackingNumber)
    {
        $names = $this->getShipmentPossibleNames($trackingNumber);

        if (empty($names)) {
            return null;
        }

        /** @var InfoInterface $info */
        $info = $this->apiService->shipping()->info()->execute();
        $services = (array) $info->getAvailableShippingServices();

        /** @var string $name */
        foreach ($names as $name) {
            $service = $this->machServiceByName($services, $name);
            if ($service) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Returns the service whose description equals the trimmed name, or false when none does.
     *
     * @param ServiceInterface[] $services
     * @param string             $name
     *
     * @return bool|ServiceInterface
     */
    private function machServiceByName(array $services, $name)
    {
        /** @var ServiceInterface $service */
        foreach ($services as $service) {
            if (trim($name) == $service->getServiceDescription()) {
                return $service;
            }
        }

        return false;
    }

    /**
     * Splits the order's shipping description into candidate service names, starting from the last piece.
     *
     * @param string $trackingNumber
     *
     * @return string[]|null
     * @throws LocalizedException
     */
    private function getShipmentPossibleNames($trackingNumber)
    {
        /** @var Track $track */
        $track = $this->getShipmentTrack($trackingNumber);

        if (empty($track)) {
            return null;
        }

        $shippingDescription = $track->getShipment()->getOrder()->getShippingDescription();
        $parts = explode(FrenetCarrier::STR_SEPARATOR, $shippingDescription);

        /**
         * Reversing the array makes it more performatic because it begins searching by the last piece.
         */
        return (array) array_reverse($parts);
    }

    /**
     * Loads the first shipment track registered with the tracking number.
     *
     * @param string $trackingNumber
     *
     * @return ShipmentTrackInterface|null
     */
    private function getShipmentTrack($trackingNumber)
    {
        /** @var SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $this->criteriaBuilder
            ->addFilter('track_number', $trackingNumber)
            ->create();

        $list = $this->trackRepository->getList($searchCriteria);

        foreach ($list->getItems() as $item) {
            return $item;
        }

        return null;
    }
}
