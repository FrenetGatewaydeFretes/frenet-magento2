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

use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Accepts only the cart items shipped on their own, skipping composite parents and virtual products.
 */
class ItemValidator implements QuoteItemValidatorInterface
{
    /**
     * @inheritDoc
     */
    public function validate(AbstractItem $item)
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
     * Returns the product behind the cart item.
     *
     * @param AbstractItem $item
     *
     * @return Product
     */
    private function getProduct(AbstractItem $item)
    {
        /** @var Product $product */
        $product = $item->getProduct();
        return $product;
    }
}
