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
use Frenet\Shipping\Model\CacheManager;
use Frenet\Shipping\Model\Quote\CouponProcessor;

/**
 * Added the coupon to the former cart-level cache key, kept so existing DI configuration still compiles.
 *
 * @deprecated 2.4.8-p4 Frenet answers are cached by the hash of each API request in CacheManager.
 * @see CacheManager
 */
class CouponGenerator implements CacheKeyGeneratorInterface
{
    /**
     * @var CouponProcessor
     */
    private $couponProcessor;

    /**
     * @param CouponProcessor $couponProcessor
     */
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
