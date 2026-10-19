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

use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Decides which cart items are sent to the Frenet API as shipping items.
 */
interface QuoteItemValidatorInterface
{
    /**
     * Checks whether the cart item should be quoted as a shipping item.
     *
     * @param AbstractItem $item
     *
     * @return bool
     */
    public function validate(AbstractItem $item);
}
