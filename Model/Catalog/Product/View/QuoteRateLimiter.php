<?php
/**
 * Frenet_Shipping
 *
 * @vendor    Frenet
 * @package   Shipping
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@frenet.com.br>
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Catalog\Product\View;

use Frenet\Shipping\Model\ConfigInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Counts quotes per visitor and per website in fixed one-minute windows kept in the Magento cache, any backend.
 */
class QuoteRateLimiter implements QuoteRateLimiterInterface
{
    /**
     * @var int
     */
    private const WINDOW = 60;

    /**
     * @var string
     */
    private const KEY_PREFIX = 'FRENET_QUOTE_LIMIT_';

    /**
     * @param CacheInterface $cache
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isAllowed(string $visitor): bool
    {
        $websiteId = (int) $this->storeManager->getStore()->getWebsiteId();
        $window = intdiv((int) $this->dateTime->gmtTimestamp(), self::WINDOW);

        return $this->consume(
            'VISITOR_' . $websiteId . '_' . hash('sha256', $visitor) . '_' . $window,
            $this->config->getProductQuoteVisitorLimit(),
            'visitor ' . $visitor
        ) && $this->consume(
            'STORE_' . $websiteId . '_' . $window,
            $this->config->getProductQuoteStoreLimit(),
            'website ' . $websiteId
        );
    }

    /**
     * Adds one quote to the counter and logs the first quote over the limit, so each blocked minute is logged once.
     *
     * @param string $counter
     * @param int $limit
     * @param string $subject
     *
     * @return bool
     */
    private function consume(string $counter, int $limit, string $subject): bool
    {
        if ($limit === 0) {
            return true;
        }

        $key = self::KEY_PREFIX . $counter;
        $count = (int) $this->cache->load($key) + 1;
        $this->cache->save((string) $count, $key, [], self::WINDOW * 2);

        if ($count === $limit + 1) {
            $this->logger->warning(
                sprintf('Frenet product quote limit of %d per minute reached by %s.', $limit, $subject)
            );
        }

        return $count <= $limit;
    }
}
