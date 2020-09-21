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

use Magento\Framework\Serialize\SerializerInterface;

class CacheKeyGenerator implements CacheKeyGeneratorInterface
{
    /**
     * @var array
     */
    private $generators;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        SerializerInterface $serializer,
        array $generators = []
    ) {
        $this->serializer = $serializer;
        $this->generators = $generators;
    }

    /**
     * @return string
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
