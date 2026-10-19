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
use Magento\Quote\Model\Quote\ItemFactory;

/**
 * Wraps a catalog product into a standalone quote item priced at its final price for the given quantity.
 */
class QuoteItemConvertor
{
    /**
     * @var ItemFactory
     */
    private $itemFactory;

    /**
     * @param ItemFactory $itemFactory
     */
    public function __construct(
        ItemFactory $itemFactory
    ) {
        $this->itemFactory = $itemFactory;
    }

    /**
     * Converts the product into a quote item that is not attached to any quote.
     *
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
     * Creates the quote item with the product ID, store, quantity, final price and row total.
     *
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
