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

use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\Cache\CacheKeyGeneratorInterface;
use Frenet\Shipping\Model\Formatters\PostcodeNormalizer;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Framework\Serialize\SerializerInterface;

class PostcodeGenerator implements CacheKeyGeneratorInterface
{
    /**
     * @var RateRequestProviderInterface
     */
    private $requestProvider;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var PostcodeNormalizer
     */
    private $postcodeNormalizer;

    public function __construct(
        PostcodeNormalizer $postcodeNormalizer,
        SerializerInterface $serializer,
        RateRequestProviderInterface $requestProvider,
        ConfigInterface $config
    ) {
        $this->serializer = $serializer;
        $this->requestProvider = $requestProvider;
        $this->config = $config;
        $this->postcodeNormalizer = $postcodeNormalizer;
    }

    /**
     * @inheritDoc
     */
    public function generate()
    {
        $destPostcode = $this->requestProvider->getRateRequest()->getDestPostcode();
        $origPostcode = $this->config->getOriginPostcode();

        return $this->postcodeNormalizer->format($destPostcode) . '-' .
            $this->postcodeNormalizer->format($origPostcode);
    }
}
