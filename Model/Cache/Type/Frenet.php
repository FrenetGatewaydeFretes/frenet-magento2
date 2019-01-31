<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Cache\Type;

use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use Magento\Framework\Config\CacheInterface;

/**
 * Class Frenet
 *
 * @package Frenet\Shipping\Cache\Type
 */
class Frenet extends TagScope implements CacheInterface
{
    /**
     * Cache type code unique among all cache types
     */
    const TYPE_IDENTIFIER = 'frenet_api_result';

    /**
     * Cache tag used to distinguish the cache type from all other cache
     */
    const CACHE_TAG = 'FRENET_API_RESULT';

    /**
     * @var \Magento\Framework\App\Cache\Manager
     */
    protected $cacheManager;

    /**
     * Frenet constructor.
     *
     * @param \Magento\Framework\App\Cache\Manager           $cacheManager
     * @param \Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool
     */
    public function __construct(
        \Magento\Framework\App\Cache\Manager $cacheManager,
        \Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool
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
     * @param bool $flag
     *
     * @return $this
     */
    public function setEnabled($flag = true)
    {
        $this->cacheManager->setEnabled([self::TYPE_IDENTIFIER], (bool) $flag);

        return $this;
    }
}
