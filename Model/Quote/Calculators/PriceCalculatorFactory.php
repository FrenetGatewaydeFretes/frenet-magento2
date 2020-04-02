<?php
/**
 * Copyright © MagedIn. All rights reserved.
 * See COPYING.txt for license details.
 *
 * @author Tiago Sampaio <tiago.sampaio@magedin.com>
 */
declare(strict_types = 1);

namespace Frenet\Shipping\Model\Quote\Calculators;

use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class PriceCalculatorFactory
 *
 * @package Frenet\Shipping\Model\Quote\Calculators
 */
class PriceCalculatorFactory
{
    /**
     * @var string
     */
    const DEFAULT_CALCULATOR_TYPE = 'default';

    /**
     * @var array
     */
    private $calculators;

    public function __construct(
        array $calculators = []
    ) {
        $this->calculators = $calculators;
    }

    /**
     * @param QuoteItem $item
     *
     * @return PriceCalculatorInterface
     */
    public function create(QuoteItem $item) : PriceCalculatorInterface
    {
        return $this->getCalculatorInstance($item);
    }

    /**
     * @param QuoteItem $item
     *
     * @return mixed
     */
    private function getCalculatorInstance(QuoteItem $item) : PriceCalculatorInterface
    {
        $type = $this->getCalculatorType($item);

        if (isset($this->calculators[$type])) {
            return $this->calculators[$type];
        }

        return $this->calculators[$this->getDefaultCalculatorType()];
    }

    /**
     * @param QuoteItem $item
     *
     * @return string
     */
    private function getCalculatorType(QuoteItem $item) : string
    {
        $type = $item->getProductType();

        if ($item->getParentItemId()) {
            $type = $item->getParentItem()->getProductType();
        }

        return $type;
    }

    /**
     * @return string
     */
    private function getDefaultCalculatorType() : string
    {
        return self::DEFAULT_CALCULATOR_TYPE;
    }
}
