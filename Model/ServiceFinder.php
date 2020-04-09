<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;

/**
 * Class ServiceFinder
 *
 * @package Frenet\Shipping\Model
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
     * @inheritdoc
     */
    public function findByTrackingNumber($trackingNumber)
    {
        $names = $this->getShipmentPossibleNames($trackingNumber);

        if (empty($names)) {
            return null;
        }

        /** @var \Frenet\ObjectType\Entity\Shipping\InfoInterface $info */
        $info = $this->apiService->shipping()->info()->execute();
        $services = (array) $info->getAvailableShippingServices();

        /** @var string $name */
        foreach ($names as $name) {
            if ($service = $this->machServiceByName($services, $name)) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @param \Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface[] $services
     * @param string                                                     $name
     * @return bool|\Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface
     */
    private function machServiceByName(array $services, $name)
    {
        /** @var \Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface $service */
        foreach ($services as $service) {
            if (trim($name) == $service->getServiceDescription()) {
                return $service;
            }
        }

        return false;
    }

    /**
     * @param string $trackingNumber
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getShipmentPossibleNames($trackingNumber)
    {
        /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
        $track = $this->getShipmentTrack($trackingNumber);

        if (empty($track)) {
            return null;
        }

        $shippingDescription = $track->getShipment()->getOrder()->getShippingDescription();
        $parts = explode(\Frenet\Shipping\Model\Carrier\Frenet::STR_SEPARATOR, $shippingDescription);

        /**
         * Reversing the array makes it more performatic because it begins searching by the last piece.
         */
        return (array) array_reverse($parts);
    }

    /**
     * @param string $trackingNumber
     * @return \Magento\Sales\Api\Data\ShipmentTrackInterface
     */
    private function getShipmentTrack($trackingNumber)
    {
        /** @var \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $this->criteriaBuilder
            ->addFilter('track_number', $trackingNumber)
            ->create();

        $list = $this->trackRepository->getList($searchCriteria);

        foreach ($list->getItems() as $item) {
            return $item;
        }
    }
}
