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

namespace Frenet\Shipping\Model\Quote\Calculators;

use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Picks the price calculator registered in DI for the item's product type, or for its parent's type on child items.
 */
class PriceCalculatorFactory
{
    /**
     * @var string
     */
    public const DEFAULT_CALCULATOR_TYPE = 'default';

    /**
     * @var PriceCalculatorInterface[]
     */
    private $calculators;

    /**
     * @param PriceCalculatorInterface[] $calculators
     */
    public function __construct(
        array $calculators = []
    ) {
        $this->calculators = $calculators;
    }

    /**
     * Returns the price calculator that matches the item's product type.
     *
     * @param QuoteItem $item
     *
     * @return PriceCalculatorInterface
     */
    public function create(QuoteItem $item): PriceCalculatorInterface
    {
        return $this->getCalculatorInstance($item);
    }

    /**
     * Looks up the calculator registered for the item's type, falling back to the default calculator.
     *
     * @param QuoteItem $item
     *
     * @return PriceCalculatorInterface
     */
    private function getCalculatorInstance(QuoteItem $item): PriceCalculatorInterface
    {
        $type = $this->getCalculatorType($item);
        if (isset($this->calculators[$type])) {
            return $this->calculators[$type];
        }
        return $this->calculators[$this->getDefaultCalculatorType()];
    }

    /**
     * Returns the item's product type, or the parent item's type when the item is a child.
     *
     * @param QuoteItem $item
     *
     * @return string
     */
    private function getCalculatorType(QuoteItem $item): string
    {
        $type = $item->getProductType();
        if ($item->getParentItemId() && $item->getParentItem()) {
            $type = $item->getParentItem()->getProductType();
        }
        return $type;
    }

    /**
     * Returns the DI key of the calculator used when no type-specific one is registered.
     *
     * @return string
     */
    private function getDefaultCalculatorType(): string
    {
        return self::DEFAULT_CALCULATOR_TYPE;
    }
}
