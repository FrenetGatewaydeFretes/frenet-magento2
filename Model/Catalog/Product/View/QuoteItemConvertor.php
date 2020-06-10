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

namespace Frenet\Shipping\Model\Catalog\Product\View;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

class QuoteItemConvertor
{
    /**
     * @var \Magento\Quote\Model\Quote\ItemFactory
     */
    private $itemFactory;

    public function __construct(
        \Magento\Quote\Model\Quote\ItemFactory $itemFactory
    ) {
        $this->itemFactory = $itemFactory;
    }

    /**
     * @param ProductInterface $product
     * @param int              $qty
     *
     * @return QuoteItem
     */
    public function convert(ProductInterface $product, int $qty = 1) : QuoteItem
    {
        return $this->createCartItem($product, $qty);
    }

    /**
     * @param ProductInterface $product
     * @param int              $qty
     *
     * @return QuoteItem
     */
    private function createCartItem(ProductInterface $product, int $qty) : QuoteItem
    {
        $item = $this->itemFactory->create();

        $item->setProduct($product);
        $item->setId($product->getId());
        $item->setStoreId($product->getStoreId());
        $item->setQty($qty);
        $item->setQtyToAdd($qty);
        $item->setPrice($product->getFinalPrice($qty));
        $item->setRowTotal($item->getPrice() * $qty);

        return $item;
    }
}
