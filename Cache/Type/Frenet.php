<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Cache\Type;

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
    const TYPE_IDENTIFIER = 'frenet_result';
    
    /**
     * Cache tag used to distinguish the cache type from all other cache
     */
    const CACHE_TAG = 'FRENET_RESULT';
    
    /**
     * @var \Magento\Framework\App\Cache\Type\FrontendPool
     */
    private $cacheFrontendPool;
    
    /**
     * @param \Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool
     */
    public function __construct(\Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool)
    {
        $this->cacheFrontendPool = $cacheFrontendPool;
    }
    
    /**
     * Retrieve cache frontend instance being decorated
     *
     * @return \Magento\Framework\Cache\FrontendInterface
     */
    protected function _getFrontend()
    {
        $frontend = parent::_getFrontend();
        if (!$frontend) {
            $frontend = $this->cacheFrontendPool->get(self::TYPE_IDENTIFIER);
            $this->setFrontend($frontend);
        }
        return $frontend;
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
}
