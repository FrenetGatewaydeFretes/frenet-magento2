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
 * Class QuoteItemValidatorInterface
 */
interface QuoteItemValidatorInterface
{
    /**
     * @param AbstractItem $item
     *
     * @return boolean
     */
    public function validate(AbstractItem $item);
}
