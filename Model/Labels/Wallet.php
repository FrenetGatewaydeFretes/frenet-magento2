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
     */
    public function __construct(
        private readonly Client $client,
        private readonly LabelsConfig $config,
        private readonly CacheInterface $cache,
        private readonly Json $json
    ) {
    }

    /**
     * Balance and label limit, or null when Frenet does not answer or labels are not configured.
     *
     * @param bool $fresh Skip the cache (after buying or cancelling).
     * @return array{balance: float, labels: int}|null
     */
    public function get(bool $fresh = false): ?array
    {
        if (!$this->config->isAvailable()) {
            return null;
        }
        if (!$fresh && ($cached = $this->cache->load(self::CACHE_KEY))) {
            $data = (array) $this->json->unserialize($cached);
            return $data ? ['balance' => (float) $data['balance'], 'labels' => (int) $data['labels']] : null;
        }
        try {
            $res = $this->client->whitelabel('GET', '/wallet');
        } catch (LabelException $e) {
            return null;
        }
        $out = [
            'balance' => (float) (Client::field($res, 'balance') ?? 0),
            'labels' => (int) (Client::field($res, 'labelLimit') ?? 0),
        ];
        $this->cache->save($this->json->serialize($out), self::CACHE_KEY, [], self::CACHE_TTL);
        return $out;
    }

    /**
     * Mercado Pago checkout of a wallet deposit (POST /wallet/deposit → PreferenceId). Frenet credits the wallet when
     * Mercado Pago confirms; the notification URL only tells this store to refresh the cached balance.
     *
     * @param float $value Amount in BRL.
     * @param string $notify URL Frenet calls when the deposit changes.
     * @param array{success: string, failure: string, pending: string} $back URLs Mercado Pago returns to.
     * @return string Checkout URL.
     * @throws LabelException
     */
    public function deposit(float $value, string $notify, array $back): string
    {
        $value = round($value, 2);
        if ($value < 1) {
            throw new LabelException(__('Type an amount of at least R$ 1,00.'));
        }
        $res = $this->client->whitelabel('POST', '/wallet/deposit', [
            'Value' => $value,
            'NotificationUrl' => $notify,
            'CallbackUrls' => [
                'SuccessUrl' => $back['success'],
                'FailureUrl' => $back['failure'],
                'PendingUrl' => $back['pending'],
            ],
        ]);
        $preference = (string) (Client::field($res, 'preferenceId') ?? '');
        if ($preference === '') {
            throw new LabelException(
                __('Frenet did not start the payment. Nothing was charged; try again or add balance in the Frenet panel.')
            );
        }
        $this->clear();
        $initPoint = (string) (Client::field($res, 'initPoint') ?? '');
        return self::allowedCheckout($initPoint, $this->config->isSimulated()) ? $initPoint : self::checkoutUrl($preference);
    }

    /**
     * Whether a payment link Frenet returned can be opened: Mercado Pago over https, or anything in simulator mode.
     *
     * @param string $url
     * @param bool $simulated
     * @return bool
     */
    public static function allowedCheckout(string $url, bool $simulated): bool
    {
        if ($url === '') {
            return false;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        return $simulated || (str_starts_with($url, 'https://') && (bool) preg_match('/(^|\.)mercadopago\.com(\.[a-z]{2})?$/', $host));
    }

    /**
     * Mercado Pago Checkout Pro URL of a preference.
     *
     * @param string $preference
     * @return string
     */
    public static function checkoutUrl(string $preference): string
    {
        return 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=' . rawurlencode($preference);
    }

    /**
     * Forgets the cached value.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->cache->remove(self::CACHE_KEY);
    }
}
