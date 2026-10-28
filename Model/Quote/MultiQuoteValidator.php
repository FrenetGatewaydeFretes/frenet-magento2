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

use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Model\WeightConverterInterface;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Allows splitting an overweight cart into packages only when multi-quote is enabled and every item fits one package.
 */
class MultiQuoteValidator implements MultiQuoteValidatorInterface
{
    /**
     * @param ConfigInterface              $config
     * @param PackageLimit                 $packageLimit
     * @param RateRequestProviderInterface $rateRequestProvider
     * @param WeightConverterInterface     $weightConverter
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PackageLimit $packageLimit,
        private readonly RateRequestProviderInterface $rateRequestProvider,
        private readonly WeightConverterInterface $weightConverter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function canProcessMultiQuote(): bool
    {
        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestProvider->getRateRequest();

        if (!$this->config->isMultiQuoteEnabled()) {
            return false;
        }

        $isUnlimited = $this->packageLimit->isUnlimited();
        $isOverweight = $this->packageLimit->isOverWeight(
            $this->weightConverter->convertToKg((float) $rateRequest->getPackageWeight())
        );

        if (!$isUnlimited && !$isOverweight) {
            return false;
        }

        /** @var AbstractItem $item */
        foreach ($rateRequest->getAllItems() as $item) {
            /**
             * If any single product is overweight then the multi quote cannot be done.
             */
            if ($this->packageLimit->isOverWeight($this->weightConverter->convertToKg((float) $item->getWeight()))) {
                return false;
            }
        }
        return true;
    }
}
