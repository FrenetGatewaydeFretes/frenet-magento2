<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Frenet wallet (GET /wallet), cached for 5 minutes. Never throws: null means "unavailable".
 * Balance is added in the Frenet panel (its own payment gateway); "Atualizar saldo" clears the cache.
 */
class Wallet
{
    private const CACHE_KEY = 'frenet_shipping_labels_wallet';
    private const CACHE_TTL = 300;

    /**
     * @param Client $client
     * @param LabelsConfig $config
     * @param CacheInterface $cache
     * @param Json $json
     * @param ResponseReader $reader
     */
    public function __construct(
        private readonly Client $client,
        private readonly LabelsConfig $config,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly ResponseReader $reader
    ) {
    }

    /**
     * Balance and label limit (cached), or null when Frenet does not answer or labels are not configured.
     *
     * @return array{balance: float, labels: int}|null
     */
    public function get(): ?array
    {
        if (!$this->config->isAvailable()) {
            return null;
        }
        $cached = $this->cache->load($this->key());
        if ($cached) {
            $data = (array) $this->json->unserialize($cached);
            return $data ? ['balance' => (float) $data['balance'], 'labels' => (int) $data['labels']] : null;
        }
        return $this->refresh();
    }

    /**
     * Balance and label limit read from Frenet now (after buying, cancelling or paying in the panel).
     *
     * @return array{balance: float, labels: int}|null
     */
    public function refresh(): ?array
    {
        if (!$this->config->isAvailable()) {
            return null;
        }
        try {
            $res = $this->client->whitelabel('GET', '/wallet');
        } catch (LabelException $e) {
            return null;
        }
        $out = [
            'balance' => (float) ($this->reader->field($res, 'balance') ?? 0),
            'labels' => (int) ($this->reader->field($res, 'labelLimit') ?? 0),
        ];
        $this->cache->save($this->json->serialize($out), $this->key(), [], self::CACHE_TTL);
        return $out;
    }

    /**
     * Forgets the cached value.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->cache->remove($this->key());
    }

    /**
     * Cache key of the current account and API.
     *
     * @return string
     */
    private function key(): string
    {
        return self::CACHE_KEY . '_' . $this->config->cacheScope();
    }
}
