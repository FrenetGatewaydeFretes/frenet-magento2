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
 * "Drop-off point": where the store hands over the packages of each carrier, like the panel's "Escolha onde postar os
 * seus pedidos" (painel.frenet.com.br/Carrier/{code}/Services), without the map.
 *
 * Carriers come from the store's services (GET /carriers/services); the points near a CEP from
 * GET /carriers/{code}/zipcode/{cep}/branches; the default point is read and saved on the store profile
 * (GET/PUT /profile/{code}/defaultbranch). Frenet uses that default point for every new label of the carrier.
 */
class Dropoff
{
    private const CACHE_KEY = 'frenet_shipping_labels_carriers';
    private const CACHE_TTL = 3600;

    /**
     * @param Client $client
     * @param CacheInterface $cache
     * @param Json $json
     * @param LabelsConfig $config
     * @param ResponseReader $reader
     * @param DropoffPoints $points
     */
    public function __construct(
        private readonly Client $client,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LabelsConfig $config,
        private readonly ResponseReader $reader,
        private readonly DropoffPoints $points
    ) {
    }

    /**
     * Carriers with at least one enabled service, by name (cached one hour).
     *
     * @return array<int, array{code: string, name: string, services: string[]}>
     * @throws LabelException
     */
    public function carriers(): array
    {
        $cached = $this->cache->load($this->cacheKey());
        if (is_string($cached) && $cached !== '') {
            return (array) $this->json->unserialize($cached);
        }
        return $this->refreshCarriers();
    }

    /**
     * Carriers read from Frenet now, replacing the cached list.
     *
     * @return array<int, array{code: string, name: string, services: string[]}>
     * @throws LabelException
     */
    public function refreshCarriers(): array
    {
        $res = $this->client->configuration('GET', '/carriers/services');
        $out = $this->points->group((array) ($this->reader->field($res, 'shippingServices') ?? []));
        $this->cache->save((string) $this->json->serialize($out), $this->cacheKey(), [], self::CACHE_TTL);
        return $out;
    }

    /**
     * Drop-off points of a carrier near a CEP. A carrier without points (Frenet answers 404) gives an empty list.
     *
     * @param string $carrier Carrier code (JTE, LOG, TOT, JAD…).
     * @param string $cep CEP, any format.
     * @return array<int, array{id: string, name: string, initials: string, address: string, type: string}>
     * @throws LabelException
     */
    public function branches(string $carrier, string $cep): array
    {
        $cep = (string) preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            throw new LabelException(__('Type a CEP with 8 digits.'));
        }
        try {
            $res = $this->client->configuration(
                'GET',
                '/carriers/' . rawurlencode($this->code($carrier)) . '/zipcode/' . $cep . '/branches'
            );
        } catch (LabelException $e) {
            if ($e->getCode() === 404) {
                return [];
            }
            throw $e;
        }
        $out = [];
        foreach ($res as $raw) {
            if (is_array($raw)) {
                $branch = $this->points->branch($raw);
                if ($branch['id'] !== '') {
                    $out[] = $branch;
                }
            }
        }
        return $out;
    }

    /**
     * Default drop-off point of the carrier, or null when the store has none.
     *
     * @param string $carrier
     * @return array{id: string, name: string, initials: string, address: string, type: string}|null
     * @throws LabelException
     */
    public function defaultBranch(string $carrier): ?array
    {
        try {
            $res = $this->client->configuration('GET', $this->profilePath($carrier));
        } catch (LabelException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
        $branch = $this->points->branch($res);
        return $branch['id'] !== '' ? $branch : null;
    }

    /**
     * Saves the default drop-off point of the carrier on the Frenet profile.
     *
     * @param string $carrier
     * @param string $pointId Point id from branches().
     * @return void
     * @throws LabelException
     */
    public function setDefault(string $carrier, string $pointId): void
    {
        $pointId = trim($pointId);
        if ($pointId === '') {
            throw new LabelException(__('Choose a drop-off point.'));
        }
        try {
            $this->client->configuration('PUT', $this->profilePath($carrier), ['Id' => $pointId]);
        } catch (LabelException $e) {
            // Live 2026-10-14: Jadlog saves (204); J&T, Loggi and Total answer 400 "Unidade não existe" even for the
            // point already saved on the profile. Tell the shopkeeper where it does work instead of a raw error.
            if (stripos($e->getMessage(), 'Unidade não existe') !== false) {
                throw new DropoffRefusedException(__(
                    'Frenet did not accept this point for this carrier ("Unidade não existe"). Nothing changed. '
                    . 'Choose it in the Frenet panel, in Shipping methods > Carriers.'
                ), $e);
            }
            throw $e;
        }
    }

    /**
     * Carrier code as Frenet expects it (letters and digits, upper case).
     *
     * @param string $carrier
     * @return string
     */
    public function code(string $carrier): string
    {
        return $this->points->code($carrier);
    }

    /**
     * Panel page of a carrier, where Frenet accepts every drop-off point.
     *
     * @param string $carrier
     * @return string
     */
    public function panelUrl(string $carrier): string
    {
        return LabelsConfig::PANEL_URL . 'Carrier/' . rawurlencode($this->code($carrier)) . '/Services';
    }

    /**
     * Profile path of the carrier's default point.
     *
     * @param string $carrier
     * @return string
     */
    private function profilePath(string $carrier): string
    {
        return '/profile/' . rawurlencode($this->code($carrier)) . '/defaultbranch';
    }

    /**
     * Cache key of the current account and API.
     *
     * @return string
     */
    private function cacheKey(): string
    {
        return self::CACHE_KEY . '_' . $this->config->cacheScope();
    }
}
