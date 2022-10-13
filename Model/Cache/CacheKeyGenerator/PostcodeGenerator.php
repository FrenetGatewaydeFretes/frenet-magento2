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

use Frenet\Shipping\Model\Config;
use Frenet\Shipping\Model\Cache\CacheKeyGeneratorInterface;
use Frenet\Shipping\Model\Formatters\PostcodeNormalizer;
use Frenet\Shipping\Service\RateRequestProvider;
use Magento\Framework\Serialize\SerializerInterface;
use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

class PostcodeGenerator extends FrenetMagentoAbstract implements CacheKeyGeneratorInterface
{
    /**
     * @var RateRequestProvider
     */
    private $requestProvider;

    /**
     * @var Config
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

    /**
     * @param PostcodeNormalizer        $postcodeNormalizer
     * @param SerializerInterface       $serializer
     * @param RateRequestProvider       $requestProvider
     * @param Config                    $config
     * @param \Psr\Log\LoggerInterface  $logger
     */
    public function __construct(
        PostcodeNormalizer $postcodeNormalizer,
        SerializerInterface $serializer,
        RateRequestProvider $requestProvider,
        Config $config,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
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
        $this->_logger->debug("postcode-generator:pre-calculate: ");//.var_export($this->rateRequestProvider, true));
        $destPostcode = $this->requestProvider->getRateRequest()->getDestPostcode();
        $origPostcode = $this->config->getOriginPostcode();

        return $this->postcodeNormalizer->format($destPostcode) . '-' .
            $this->postcodeNormalizer->format($origPostcode);
    }
}
