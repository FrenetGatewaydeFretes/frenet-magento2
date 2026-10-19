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
use Frenet\Shipping\Model\ConfigInterface;

/**
 * Added the Multi Quote mode to the former cart-level cache key, kept for existing DI configuration.
 *
 * @deprecated 2.4.8-p4 Frenet answers are cached by the hash of each API request in CacheManager.
 * @see CacheManager
 */
class MultiQuoteGenerator implements CacheKeyGeneratorInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function generate()
    {
        return $this->config->isMultiQuoteEnabled() ? 'multi' : 'single';
    }
}
