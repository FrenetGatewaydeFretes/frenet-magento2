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

namespace Frenet\Shipping\Model\Quote;

/**
 * Class MultiQuoteValidator
 *
 * @package Frenet\Shipping\Model\Quote
 */
class MultiQuoteValidator implements MultiQuoteValidatorInterface
{
    /**
     * @var \Frenet\Shipping\Model\Config
     */
    private $config;

    /**
     * @var \Frenet\Shipping\Model\Packages\PackageLimit
     */
    private $packageLimit;

    public function __construct(
        \Frenet\Shipping\Model\Config $config,
        \Frenet\Shipping\Model\Packages\PackageLimit $packageLimit
    ) {
        $this->config = $config;
        $this->packageLimit = $packageLimit;
    }

    /**
     * @inheritDoc
     */
    public function canProcessMultiQuote(\Magento\Quote\Model\Quote\Address\RateRequest $rateRequest)
    {
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
