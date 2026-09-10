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

declare(strict_types=1);

namespace Frenet\Shipping\Model\Packages;

use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\ObjectType\Entity\Shipping\Quote\ServiceFactory;

/**
 * Merges a split-cart quote: Correios is summed per package, the other carriers come from the full unlimited call.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class PackageMatching
{
    /**
     * @var array
     */
    private array $results = [];

    /**
     * @var array
     */
    private array $fullResults = [];

    /**
     * @var array
     */
    private array $services = [];

    public function __construct(
        private readonly ServiceFactory $serviceFactory
    ) {
    }

    /**
     * Consolidates the per-package results ('full' plus one entry per package) into a single service list.
     *
     * @param array $results
     *
     * @return array
     */
    public function match(array $results): array
    {
        $this->init($results);
        return $this->matchResults();
    }

    /**
     * Binds each package's Correios services, then appends the carriers kept from the full call.
     *
     * @return array
     */
    private function matchResults(): array
    {
        /** @var array $services */
        foreach ($this->results as $services) {
            $this->prepareServices($services);
        }

        return $this->buildServicesResult();
    }

    /**
     * Feeds one package's non-error Correios services into the running per-code totals.
     *
     * @param array $services
     *
     * @return $this
     */
    private function prepareServices(array $services): self
    {
        /** @var Service $service */
        foreach ($services as $service) {
            if ($service->getCarrier() !== 'Correios') {
                continue;
            }

            if ($service->isError()) {
                continue;
            }

            $this->appendService($service);
        }

        return $this;
    }

    /**
     * Adds a service's price to its per-code total and keeps the slowest delivery time seen so far.
     *
     * @param Service $service
     *
     * @return $this
     */
    private function appendService(Service $service): self
    {
        $serviceCode = $service->getServiceCode();

        $object = isset($this->services[$serviceCode]) ? $this->services[$serviceCode] : $this->getNewEmpty();

        $serviceDescription = $service->getServiceDescription();
        $carrier = $service->getCarrier();
        $deliveryTime = $object['delivery_time'];
        $originalDeliveryTime = $object['original_delivery_time'];
        $originalShippingPrice = $object['original_shipping_price'] += $service->getOriginalShippingPrice();
        $responseTime = $service->getResponseTime();
        $shippingPrice = $object['shipping_price'] += $service->getShippingPrice();

        if ($service->getDeliveryTime() > $deliveryTime) {
            $deliveryTime = $service->getDeliveryTime();
        }

        if ($service->getOriginalDeliveryTime() > $originalDeliveryTime) {
            $deliveryTime = $service->getDeliveryTime();
        }

        $object = [
            'carrier'                 => $carrier,
            'delivery_time'           => $deliveryTime,
            'error'                   => false,
            'original_delivery_time'  => $originalDeliveryTime,
            'original_shipping_price' => $originalShippingPrice,
            'response_time'           => $responseTime,
            'service_code'            => $serviceCode,
            'service_description'     => $serviceDescription,
            'shipping_price'          => $shippingPrice,
        ];

        $this->services[$serviceCode] = $object;

        return $this;
    }

    /**
     * Builds the bound Correios services and merges them with the carriers kept from the full call.
     *
     * @return array
     */
    private function buildServicesResult(): array
    {
        $results = [];

        /** @var array $serviceData */
        foreach ($this->services as $serviceData) {
            $results[] = $this->serviceFactory->create()->setData($serviceData);
        }

        return array_merge($results, $this->fullResults);
    }

    /**
     * Returns the zeroed accumulator a service code starts from before the first package is added.
     *
     * @return array
     */
    private function getNewEmpty(): array
    {
        return [
            'carrier'                 => null,
            'delivery_time'           => 0,
            'error'                   => false,
            'original_delivery_time'  => 0,
            'original_shipping_price' => 0.0000,
            'response_time'           => 0.0000,
            'service_code'            => null,
            'service_description'     => null,
            'shipping_price'          => 0.0000,
        ];
    }

    /**
     * Splits the incoming results into the full-call list and the per-package lists.
     *
     * @param array $results
     *
     * @return $this
     */
    private function init(array $results): self
    {
        $this->fullResults = $results['full'] ?? [];
        unset($results['full']);
        $this->results = $results;
        $this->processFullResults();

        return $this;
    }

    /**
     * Keeps only the non-Correios services from the full call; Correios is rebuilt per package by the matcher.
     *
     * @return $this
     */
    private function processFullResults(): self
    {
        /** @var Service $service */
        foreach ($this->fullResults as $index => $service) {
            if ($service->isError() || $service->getCarrier() === 'Correios') {
                unset($this->fullResults[$index]);
            }
        }

        return $this;
    }
}
