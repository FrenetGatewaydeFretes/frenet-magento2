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

namespace Frenet\Shipping\Model;

use Frenet\Framework\Data\Serializer;
use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\Shipping\Model\Cache\Type\Frenet as FrenetCacheType;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Keeps each Frenet API answer under the hash of its request, so only an identical request reuses it.
 */
class CacheManager
{
    /**
     * @var string
     */
    private const KEY_PREFIX = 'FRENET_QUOTE_';

    /**
     * @param SerializerInterface $serializer
     * @param StateInterface      $cacheState
     * @param CacheInterface      $cache
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly StateInterface $cacheState,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Returns the services stored for the request, or null when the cache is off or holds nothing for it.
     *
     * @param string $requestKey
     *
     * @return Service[]|null
     */
    public function load(string $requestKey): ?array
    {
        if (!$this->isCacheEnabled()) {
            return null;
        }

        $data = $this->cache->load(self::KEY_PREFIX . $requestKey);

        if (!$data) {
            return null;
        }

        $services = [];

        foreach ((array) $this->serializer->unserialize($data) as $serviceData) {
            $services[] = (new Service(new Serializer()))->setData((array) $serviceData);
        }

        return $services ?: null;
    }

    /**
     * Stores a non-empty API answer for the request while the Frenet cache type is enabled.
     *
     * @param string    $requestKey
     * @param Service[] $services
     *
     * @return bool
     */
    public function save(string $requestKey, array $services): bool
    {
        if (!$services || !$this->isCacheEnabled()) {
            return false;
        }

        return $this->cache->save(
            (string) $this->serializer->serialize(array_map(fn (Service $service) => $service->getData(), $services)),
            self::KEY_PREFIX . $requestKey,
            [FrenetCacheType::CACHE_TAG]
        );
    }

    /**
     * Checks whether the Frenet API result cache type is enabled.
     *
     * @return bool
     */
    private function isCacheEnabled(): bool
    {
        return (bool) $this->cacheState->isEnabled(FrenetCacheType::TYPE_IDENTIFIER);
    }
}
