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

use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Packages\PackageItemDistributor;
use Frenet\Shipping\Model\Packages\PackageLimit;
use Frenet\Shipping\Model\WeightConverterInterface;

/**
 * Allows splitting an overweight cart into packages only when multi-quote is enabled and every item fits one package.
 */
class MultiQuoteValidator implements MultiQuoteValidatorInterface
{
    /**
     * @param ConfigInterface              $config
     * @param PackageLimit                 $packageLimit
     * @param PackageItemDistributor       $itemDistributor
     * @param DimensionsExtractorInterface $dimensionsExtractor
     * @param WeightConverterInterface     $weightConverter
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PackageLimit $packageLimit,
        private readonly PackageItemDistributor $itemDistributor,
        private readonly DimensionsExtractorInterface $dimensionsExtractor,
        private readonly WeightConverterInterface $weightConverter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function canProcessMultiQuote(): bool
    {
        if (!$this->config->isMultiQuoteEnabled()) {
            return false;
        }

        foreach ($this->itemDistributor->distribute() as $entry) {
            $this->dimensionsExtractor->setProductByCartItem($entry['item']);
            $unitWeight = $this->weightConverter->convertToKg((float) $this->dimensionsExtractor->getWeight());

            if ($this->packageLimit->isOverWeight($unitWeight)) {
                return false;
            }
        }

        return true;
    }
}
