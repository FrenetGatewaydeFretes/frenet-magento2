<?php
/**
 * Frenet Shipping Gateway — CEP → address through the Frenet API, cached for 30 days.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Cep;

use Frenet\Shipping\Api\CepLookupInterface;
use Frenet\Shipping\Api\Data\CepAddressInterface;
use Frenet\Shipping\Model\Extras\Settings;
use Frenet\Shipping\Model\Labels\Client;
use Frenet\Shipping\Model\Labels\LabelException;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;

class CepLookup implements CepLookupInterface
{
    private const CACHE_PREFIX = 'frenet_shipping_cep_';
    private const CACHE_TAG = 'frenet_shipping_cep';
    private const CACHE_TTL = 2592000;

    /**
     * @param Client $client
     * @param CacheInterface $cache
     * @param Json $json
     * @param RegionFactory $regionFactory
     * @param Settings $settings
     */
    public function __construct(
        private readonly Client $client,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly RegionFactory $regionFactory,
        private readonly Settings $settings
    ) {
    }

    /**
     * Only digits, exactly 8 of them; otherwise ''.
     *
     * @param string $cep
     * @return string
     */
    public static function normalize(string $cep): string
    {
        $digits = (string) preg_replace('/\D/', '', $cep);
        return strlen($digits) === 8 ? $digits : '';
    }

    /**
     * @inheritdoc
     */
    public function lookup(string $cep): CepAddressInterface
    {
        $digits = self::normalize($cep);
        if ($digits === '' || !$this->settings->cepAutofill()) {
            throw new NoSuchEntityException(__('Invalid CEP.'));
        }
        $cached = $this->cache->load(self::CACHE_PREFIX . $digits);
        $data = $cached ? (array) $this->json->unserialize($cached) : null;
        if ($data === null) {
            try {
                $api = $this->client->address($digits);
            } catch (LabelException $e) {
                throw new NoSuchEntityException(__('CEP not found.'), $e);
            }
            if (empty($api['City'])) {
                throw new NoSuchEntityException(__('CEP not found.'));
            }
            $data = [
                'street' => (string) ($api['Street'] ?? ''),
                'district' => (string) ($api['District'] ?? ''),
                'city' => (string) $api['City'],
                'uf' => strtoupper((string) ($api['UF'] ?? '')),
            ];
            $this->cache->save($this->json->serialize($data), self::CACHE_PREFIX . $digits, [self::CACHE_TAG], self::CACHE_TTL);
        }
        $region = $this->regionFactory->create()->loadByCode($data['uf'], 'BR');
        return new CepAddress(
            substr($digits, 0, 5) . '-' . substr($digits, 5),
            $data['street'],
            $data['district'],
            $data['city'],
            $data['uf'],
            (int) $region->getId()
        );
    }
}
