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

namespace Frenet\Shipping\Model\Cache\Type;

use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use Magento\Framework\Config\CacheInterface;

/**
 * Dedicated cache type for Frenet API answers, so they can be flushed or disabled apart from other caches.
 */
class Frenet extends TagScope implements CacheInterface
{
    /**
     * Cache type code unique among all cache types
     *
     * @var string
     */
    public const TYPE_IDENTIFIER = 'frenet_api_result';

    /**
     * Cache tag used to distinguish the cache type from all other cache
     *
     * @var string
     */
    public const CACHE_TAG = 'FRENET_API_RESULT';

    /**
     * @var Manager
     */
    protected $cacheManager;

    /**
     * @param Manager      $cacheManager
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(
        Manager $cacheManager,
        FrontendPool $cacheFrontendPool
    ) {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);

        $this->cacheManager = $cacheManager;
    }

    /**
     * Retrieve cache tag name
     *
     * @return string
     */
    public function getTag()
    {
        return self::CACHE_TAG;
    }

    /**
     * Enables or disables the Frenet cache type through the application cache manager.
     *
     * @param bool $flag
     *
     * @return $this
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function setEnabled($flag = true)
    {
        $this->cacheManager->setEnabled([self::TYPE_IDENTIFIER], (bool) $flag);

        return $this;
    }
}
