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

use Frenet\Shipping\Api\QuoteItemValidatorInterface;

/**
 * Class ItemValidator
 */
class ItemValidator implements QuoteItemValidatorInterface
{
    /**
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     *
     * @return bool
     */
    public function validate(\Magento\Quote\Api\Data\CartItemInterface $item)
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
     * @return bool|\Magento\Catalog\Model\Product
     */
    private function getProduct(\Magento\Quote\Api\Data\CartItemInterface $item)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $item->getProduct();
        return $product;
    }
}
