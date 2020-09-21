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
 * Class ItemValidator
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
     * @param AbstractItem $item
     *
     * @return bool|Product
     */
    private function getProduct(AbstractItem $item)
    {
        /** @var Product $product */
        $product = $item->getProduct();
        return $product;
    }
}
