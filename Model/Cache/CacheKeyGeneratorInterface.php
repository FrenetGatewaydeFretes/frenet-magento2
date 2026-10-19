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

namespace Frenet\Shipping\Model\Cache;

use Frenet\Shipping\Model\CacheManager;

/**
 * Builds one part of the former cart-level cache key, kept so existing DI configuration still compiles.
 *
 * @deprecated 2.4.8-p4 Frenet answers are cached by the hash of each API request in CacheManager.
 * @see CacheManager
 */
interface CacheKeyGeneratorInterface
{
    /**
     * Returns this generator's piece of the cart-level cache key.
     *
     * @return string
     */
    public function generate();
}
