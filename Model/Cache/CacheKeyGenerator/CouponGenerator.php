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

namespace Frenet\Shipping\Model\Cache\CacheKeyGenerator;

use Frenet\Shipping\Model\Cache\CacheKeyGeneratorInterface;
use Frenet\Shipping\Model\Quote\CouponProcessor;

class CouponGenerator implements CacheKeyGeneratorInterface
{
    /**
     * @var CouponProcessor
     */
    private $couponProcessor;

    public function __construct(
        CouponProcessor $couponProcessor
    ) {
        $this->couponProcessor = $couponProcessor;
    }

    /**
     * @inheritDoc
     */
    public function generate()
    {
        return $this->couponProcessor->getCouponCode() ?: 'no-coupon';
    }
}
