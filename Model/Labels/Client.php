<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Frenet API client for labels (validated live on 2026-09-30):
 * - api.frenet.com.br: quote (all services) and tracking, header "token";
 * - WhiteLabel (/shipments, /orders, /wallet): headers "token" + "x-partner-token", camelCase answers,
 *   errors with .NET stack traces. The frenet-php SDK does not cover WhiteLabel, so this uses Guzzle.
 */
class Client
{
    public const API_BASE = 'https://api.frenet.com.br';
    private const TIMEOUT_QUOTE = 8;
    private const TIMEOUT_LABELS = 45;

    /**
     * @param ClientFactory $clientFactory
     * @param LabelsConfig $config
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly LabelsConfig $config,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Every Frenet service for the package, normalized and sorted by price (errors left out).
     *
     * @param string $fromCep
     * @param string $toCep
     * @param float $value
     * @param array $items ShippingItemArray rows.
     * @return array<int, array{code: string, carrier: string, carrier_code: string, name: string, price: float, days: int}>
     * @throws LabelException
     */
    public function quoteAll(string $fromCep, string $toCep, float $value, array $items): array
    {
        $data = $this->request('POST', self::API_BASE . '/shipping/quote', [
            'SellerCEP' => preg_replace('/\D/', '', $fromCep),
            'RecipientCEP' => preg_replace('/\D/', '', $toCep),
            'ShipmentInvoiceValue' => $value,
            'RecipientCountry' => 'BR',
            'ShippingItemArray' => $items,
        ], false, self::TIMEOUT_QUOTE);
        $out = [];
        // "ShippingSevicesArray" (sic) is the field name of the Frenet API.
        foreach ((array) ($data['ShippingSevicesArray'] ?? []) as $service) {
            if (!empty($service['Error']) || !isset($service['ShippingPrice'])) {
                continue;
            }
            $out[] = [
                'code' => (string) ($service['ServiceCode'] ?? ''),
                'carrier' => (string) ($service['Carrier'] ?? ''),
                'carrier_code' => (string) ($service['CarrierCode'] ?? ''),
                'name' => (string) ($service['ServiceDescription'] ?? ''),
                'price' => (float) str_replace(',', '.', (string) $service['ShippingPrice']),
                'days' => (int) ($service['DeliveryTime'] ?? 0),
            ];
        }
        usort($out, static fn ($a, $b) => $a['price'] <=> $b['price']);
        return $out;
    }

    /**
     * GET /CEP/Address/{cep} (customer token only): street, district, city and state of a CEP.
     *
     * @param string $cep 8 digits.
     * @return array
     * @throws LabelException
     */
    public function address(string $cep): array
    {
        return $this->request('GET', self::API_BASE . '/CEP/Address/' . rawurlencode($cep), null, false, self::TIMEOUT_QUOTE);
    }

    /**
     * POST /tracking/trackinginfo (customer token only).
     *
     * @param string $serviceCode
     * @param string $trackingNumber
     * @return array
     * @throws LabelException
     */
    public function tracking(string $serviceCode, string $trackingNumber): array
    {
        return $this->request('POST', self::API_BASE . '/tracking/trackinginfo', [
            'ShippingServiceCode' => $serviceCode,
            'TrackingNumber' => $trackingNumber,
        ], false, self::TIMEOUT_QUOTE);
    }

    /**
     * WhiteLabel call (shipments, orders, wallet).
     *
     * @param string $method
     * @param string $path Path after /v1, e.g. "/shipments".
     * @param mixed $body
     * @param string|null $printingFormat x-printing-format; the setting when null.
     * @return array
     * @throws LabelException
     */
    public function whitelabel(string $method, string $path, $body = null, ?string $printingFormat = null): array
    {
        return $this->request(
            $method,
            $this->config->apiUrl() . $path,
            $body,
            true,
            self::TIMEOUT_LABELS,
            $printingFormat ?? $this->config->printingFormat()
        );
    }

    /**
     * Reads $key in camelCase or PascalCase (WhiteLabel answers camelCase, the docs show PascalCase).
     *
     * @param array $data
     * @param int|string $key
     * @return mixed
     */
    public static function field(array $data, $key)
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
        if (is_string($key) && array_key_exists(ucfirst($key), $data)) {
            return $data[ucfirst($key)];
        }
        return null;
    }

    /**
     * Hides the store token that Frenet embeds (base64) in label and declaration URLs (?p=...).
     *
     * @param string $text
     * @return string
     */
    public static function redact(string $text): string
    {
        return (string) preg_replace('/([?&]p=)[^&"\s]+/', '$1***', $text);
    }

    /**
     * Keeps only the human part of a Frenet error and explains the known ones.
     *
     * @param string $message
     * @return string
     */
    public static function cleanError(string $message): string
    {
        if (stripos($message, 'acesso negado para o parceiro') !== false) {
            return (string) __('Frenet refused the partner token. Check the partner token in the labels settings. Nothing was charged.');
        }
        if (stripos($message, 'acesso negado para o cliente') !== false) {
            return (string) __('Frenet refused the customer token. Check the Frenet token. Nothing was charged.');
        }
        if (stripos($message, 'nota fiscal') !== false) {
            return (string) __('This service requires an invoice (NF-e). Paste the NF-e key of the order and try again. Nothing was charged.');
        }
        $message = (string) preg_replace('/\s*-->.*$/s', '', $message);
        $message = (string) preg_replace('/(Detalhes )?identificador:\s*[0-9a-f-]{36}\s*/i', '', $message);
        return trim($message);
    }

    /**
     * Sends the request and decodes the JSON answer.
     *
     * @param string $method
     * @param string $url
     * @param mixed $body
     * @param bool $partner
     * @param int $timeout
     * @param string $printingFormat
     * @return array
     * @throws LabelException
     */
    private function request(
        string $method,
        string $url,
        $body,
        bool $partner,
        int $timeout,
        string $printingFormat = 'A4'
    ): array {
        $token = $this->config->storeToken();
        if ($token === '') {
            throw new LabelException(__('Frenet token not configured.'));
        }
        $headers = ['token' => $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($partner) {
            $headers['x-partner-token'] = $this->config->partnerToken();
            // Frenet ignores "10x15" and prints A4; "A6" (105x148 mm) is its thermal 10x15 label (tested 2026-10-02).
            $headers['x-printing-format'] = $printingFormat === '10x15' ? 'A6' : 'A4';
        }
        $options = [
            RequestOptions::HEADERS => $headers,
            RequestOptions::TIMEOUT => $timeout,
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::HTTP_ERRORS => false,
        ];
        if ($body !== null) {
            $options[RequestOptions::BODY] = $this->json->serialize($body);
        }
        try {
            /** @var HttpClient $client */
            $client = $this->clientFactory->create();
            $response = $client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            $this->logger->error('Frenet labels ' . $method . ' ' . $url . ': ' . $e->getMessage());
            throw new LabelException(__('Frenet did not answer (%1).', $e->getMessage()), $e);
        }
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $data = [];
        if ($raw !== '') {
            try {
                $data = (array) $this->json->unserialize($raw);
            } catch (\InvalidArgumentException $e) {
                $data = [];
            }
        }
        if ($status < 200 || $status >= 300) {
            $message = (string) (self::field($data, 'message') ?? self::field($data, 'title') ?? ('HTTP ' . $status));
            $details = (array) (self::field($data, 'details') ?? []);
            if ($details && is_array($details[0] ?? null)) {
                $message .= ' ' . (string) (self::field($details[0], 'message') ?? '');
            }
            $this->logger->error(
                'Frenet labels ' . $method . ' ' . $url . ' HTTP ' . $status . ': ' . self::redact(substr($raw, 0, 500))
            );
            throw new LabelException(__('%1', self::cleanError(trim($message))));
        }
        return $data;
    }
}
