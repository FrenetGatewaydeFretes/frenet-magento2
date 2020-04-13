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

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Frenet\Shipping\Model\Cache\Type\Frenet as FrenetCacheType;

/**
 * Class CacheManager
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
     * @var \Frenet\Shipping\Api\QuoteItemValidatorInterface
     */
    private $quoteItemValidator;

    /**
     * @var \Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface
     */
    private $itemQuantityCalculator;

    /**
     * @var \Frenet\Shipping\Model\Formatters\PostcodeNormalizer
     */
    private $postcodeNormalizer;

    /**
     * @var Quote\CouponProcessor
     */
    private $couponProcessor;

    public function __construct(
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\App\Cache\StateInterface $cacheState,
        \Magento\Framework\App\CacheInterface $cache,
        \Frenet\Shipping\Api\QuoteItemValidatorInterface $quoteItemValidator,
        \Frenet\Shipping\Model\Quote\ItemQuantityCalculatorInterface $itemQuantityCalculator,
        \Frenet\Shipping\Model\Formatters\PostcodeNormalizer $postcodeNormalizer,
        \Frenet\Shipping\Model\Quote\CouponProcessor $couponProcessor,
        Config $config
    ) {
        $this->serializer = $serializer;
        $this->cacheState = $cacheState;
        $this->cache = $cache;
        $this->config = $config;
        $this->quoteItemValidator = $quoteItemValidator;
        $this->itemQuantityCalculator = $itemQuantityCalculator;
        $this->couponProcessor = $couponProcessor;
        $this->postcodeNormalizer = $postcodeNormalizer;
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
        if (!$this->isCacheEnabled()) {
            return false;
        }

        $identifier = $this->generateCacheKey($request);
        $lifetime = null;
        $tags = [FrenetCacheType::CACHE_TAG];

        return $this->cache->save($this->prepareBeforeSaving($services), $identifier, $tags, $lifetime);
    }

    /**
     * @param $data
     *
     * @return array
     */
    private function prepareAfterLoading($data)
    {
        $newData  = [];
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

        /** @var \Frenet\ObjectType\Entity\Shipping\QuoteInterface $service */
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

            $qty = (float) $this->itemQuantityCalculator->calculate($item);

            $items[$productId] = $qty;
        }

        ksort($items);

        $cacheKey = $this->serializer->serialize([
            $this->postcodeNormalizer->format($origPostcode),
            $this->postcodeNormalizer->format($destPostcode),
            $items,
            $this->couponProcessor->getCouponCode(),
            $this->config->isMultiQuoteEnabled() ? 'multi' : null
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
}
