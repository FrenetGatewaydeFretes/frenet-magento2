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

namespace Frenet\Shipping\Api;

/**
 * Class QuoteItemValidatorInterface
 */
interface QuoteItemValidatorInterface
{
    /**
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     *
     * @return boolean
     */
    public function validate(\Magento\Quote\Api\Data\CartItemInterface $item);
}
