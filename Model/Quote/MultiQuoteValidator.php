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

namespace Frenet\Shipping\Model\Quote;

use Frenet\Shipping\Model\Config;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Class MultiQuoteValidator
 */
class MultiQuoteValidator implements MultiQuoteValidatorInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var PackageLimit
     */
    private $packageLimit;

    /**
     * @var RateRequestProvider
     */
    private $rateRequestProvider;

    public function __construct(
        Config $config,
        PackageLimit $packageLimit,
        RateRequestProvider $rateRequestProvider
    ) {
        $this->config = $config;
        $this->packageLimit = $packageLimit;
        $this->rateRequestProvider = $rateRequestProvider;
    }

    /**
     * @inheritDoc
     */
    public function canProcessMultiQuote() : bool
    {
        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestProvider->getRateRequest();

        if (!$this->config->isMultiQuoteEnabled()) {
            return false;
        }

        $isUnlimited = $this->packageLimit->isUnlimited();
        $isOverweight = $this->packageLimit->isOverWeight((float) $rateRequest->getPackageWeight());

        if (!$isUnlimited && !$isOverweight) {
            return false;
        }

        /** @var \Magento\Quote\Model\Quote\Item|\Magento\Quote\Api\Data\CartItemInterface $item */
        foreach ($rateRequest->getAllItems() as $item) {
            /**
             * If any single product is overweight then the multi quote cannot be done.
             */
            if ($this->packageLimit->isOverWeight((float) $item->getWeight())) {
                return false;
            }
        }

        return true;
    }
}
