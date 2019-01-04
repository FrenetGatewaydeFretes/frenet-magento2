<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Frenet\Shipping\Model\Cache\Type\Frenet as FrenetCacheType;

/**
 * Class CacheManager
 *
 * @package Frenet\Shipping\Model
 */
class CacheManager
{
    /**
     * @var \Magento\Framework\App\Cache\StateInterface
     */
    private $cacheState;
    
    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    private $cache;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;
    
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var \Frenet\Shipping\Api\QuoteItemValidator
     */
    private $quoteItemValidator;
    
    public function __construct(
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\App\Cache\StateInterface $cacheState,
        \Magento\Framework\App\CacheInterface $cache,
        \Frenet\Shipping\Api\QuoteItemValidator $quoteItemValidator,
        Config $config
    ) {
        $this->serializer = $serializer;
        $this->cacheState = $cacheState;
        $this->cache = $cache;
        $this->config = $config;
        $this->quoteItemValidator = $quoteItemValidator;
    }
    
    /**
     * @param RateRequest $request
     *
     * @return bool
     */
    public function load(RateRequest $request)
    {
        if (!$this->isCacheEnabled()) {
            return false;
        }
        
        $data = $this->cache->load($this->generateCacheKey($request));
        
        if ($data) {
            $data = $this->prepareAfterLoading($data);
        }
        
        return $data;
    }
    
    /**
     * @param array       $services
     * @param RateRequest $request
     *
     * @return bool
     */
    public function save(array $services, RateRequest $request)
    {
        $identifier = $this->generateCacheKey($request);
        $lifetime   = null;
        $tags       = [FrenetCacheType::CACHE_TAG];
        
        return $this->cache->save($this->prepareBeforeSaving($services), $identifier, $tags, $lifetime);
    }
    
    /**
     * @param $data
     *
     * @return array
     */
    private function prepareAfterLoading($data)
    {
        $newData = [];
    
        $services = $this->serializer->unserialize($data);
        
        /** @var array $service */
        foreach ($services as $service) {
            $newData[] = ($this->createServiceInstance())->setData($service);
        }
        
        return $newData;
    }
    
    /**
     * @param array $services
     *
     * @return bool|string
     */
    private function prepareBeforeSaving(array $services)
    {
        $newData = [];
        
        /** @var \Frenet\ObjectType\Entity\Shipping\QuoteInterface $services */
        foreach ($services as $service) {
            $newData[] = $service->getData();
        }
    
        return $this->serializer->serialize($newData);
    }
    
    /**
     * @return string
     */
    private function generateCacheKey(RateRequest $request)
    {
        $destPostcode = $request->getDestPostcode();
        $origPostcode = $this->config->getOriginPostcode();
        $items = [];
        
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($request->getAllItems() as $item) {
            if (!$this->quoteItemValidator->validate($item)) {
                continue;
            }
            
            $productId = (int) $item->getProductId();
            
            if ($item->getParentItem()) {
                $productId = $item->getParentItem()->getProductId() . '-' . $productId;
            }
            
            $qty = (float) $item->getQty();
            
            $items[$productId] = $qty;
        }
        
        ksort($items);
        
        $cacheKey = $this->serializer->serialize([
            $this->normalizePostcode($origPostcode),
            $this->normalizePostcode($destPostcode),
            $items
        ]);
        
        return $cacheKey;
    }
    
    /**
     * @return bool
     */
    private function isCacheEnabled()
    {
        return (bool) $this->cacheState->isEnabled(FrenetCacheType::TYPE_IDENTIFIER);
    }
    
    /**
     * @return \Frenet\ObjectType\Entity\Shipping\Quote\Service
     */
    private function createServiceInstance()
    {
        return new \Frenet\ObjectType\Entity\Shipping\Quote\Service(
            new \Frenet\Framework\Data\Serializer()
        );
    }
    
    /**
     * @param string $postcode
     *
     * @return string|string[]|null
     */
    private function normalizePostcode($postcode)
    {
        $postcode = preg_replace('/[^0-9]/', null, $postcode);
        $postcode = str_pad($postcode, 8, '0', STR_PAD_LEFT);
        return $postcode;
    }
}
