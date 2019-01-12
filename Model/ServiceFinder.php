<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

/**
 * Class ServiceFinder
 * @package Frenet\Shipping\Model
 */
class ServiceFinder
{
    /**
     * @var ApiService
     */
    private $apiService;

    /**
     * @var \Magento\Sales\Api\ShipmentTrackRepositoryInterface
     */
    private $trackRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(
        ApiService $apiService,
        \Magento\Sales\Api\ShipmentTrackRepositoryInterface $trackRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $criteriaBuilder
    ) {
        $this->apiService = $apiService;
        $this->trackRepository = $trackRepository;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @param $trackingNumber
     * @return \Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function findByTrackingNumber($trackingNumber)
    {
        $name = $this->getShipmentName($trackingNumber);

        /** @var \Frenet\ObjectType\Entity\Shipping\InfoInterface $info */
        $info = $this->apiService->shipping()->info()->execute();

        /** @var \Frenet\ObjectType\Entity\Shipping\Info\ServiceInterface $service */
        foreach ($info->getAvailableShippingServices() as $service) {
            if ($name == $service->getServiceDescription()) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @param string $trackingNumber
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getShipmentName($trackingNumber)
    {
        /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
        $track = $this->getShipmentTrack($trackingNumber);

        if (empty($track)) {
            return null;
        }

        $shippingDescription = $track->getShipment()->getOrder()->getShippingDescription();
        $parts = explode(' - ', $shippingDescription);

        if (count($parts) >= 2) {
            return $parts[1];
        }

        return null;
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
