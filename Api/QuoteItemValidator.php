<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Api;

/**
 * Class QuoteItemValidator
 *
 * @package Frenet\Shipping\Api
 */
interface QuoteItemValidator
{
    /**
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     *
     * @return boolean
     */
    public function validate(\Magento\Quote\Api\Data\CartItemInterface $item);
}
