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

use Magento\Catalog\Model\Product;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Class ItemValidator
 *
 * @package Frenet\Shipping\Model\Quote
 */
class ItemValidator implements QuoteItemValidatorInterface
{
    /**
     * @param CartItemInterface $item
     *
     * @return bool
     */
    public function validate(CartItemInterface $item)
    {
        if ($this->getProduct($item)->isComposite()) {
            return false;
        }

        if ($this->getProduct($item)->isVirtual()) {
            return false;
        }

        return true;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return bool|Product
     */
    private function getProduct(CartItemInterface $item)
    {
        /** @var Product $product */
        $product = $item->getProduct();
        return $product;
    }
}
