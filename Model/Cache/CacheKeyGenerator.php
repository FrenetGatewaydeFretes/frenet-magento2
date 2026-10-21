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
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Joined the former cart-level cache key parts, kept so existing DI configuration still compiles.
 *
 * @deprecated 2.4.8-p4 Frenet answers are cached by the hash of each API request in CacheManager.
 * @see CacheManager
 */
class CacheKeyGenerator implements CacheKeyGeneratorInterface
{
    /**
     * @var CacheKeyGeneratorInterface[]
     */
    private $generators;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param SerializerInterface          $serializer
     * @param CacheKeyGeneratorInterface[] $generators
     */
    public function __construct(
        SerializerInterface $serializer,
        array $generators = []
    ) {
        $this->serializer = $serializer;
        $this->generators = $generators;
    }

    /**
     * @inheritDoc
     */
    public function generate()
    {
        $cacheKey = [];

        /** @var CacheKeyGeneratorInterface $generator */
        foreach ($this->generators as $generator) {
            $cacheKey[] = $generator->generate();
        }

        return $this->serializer->serialize($cacheKey);
    }
}
