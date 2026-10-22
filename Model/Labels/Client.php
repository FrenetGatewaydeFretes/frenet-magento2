<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Http\Message\ResponseInterface;
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
     * @param ResponseReader $reader
     */
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly LabelsConfig $config,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly ResponseReader $reader
    ) {
    }

    /**
     * Every Frenet service for the package, normalized and sorted by price (errors left out).
     *
     * @param string $fromCep
     * @param string $toCep
     * @param float $value
     * @param mixed[] $items ShippingItemArray rows.
     * @return array<int, array{
     *     code: string,
     *     carrier: string,
     *     carrier_code: string,
     *     name: string,
     *     price: float,
     *     days: int
     * }>
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
        usort($out, static fn ($left, $right) => $left['price'] <=> $right['price']);
        return $out;
    }

    /**
     * GET /CEP/Address/{cep} (customer token only): street, district, city and state of a CEP.
     *
     * @param string $cep 8 digits.
     * @return mixed[]
     * @throws LabelException
     */
    public function address(string $cep): array
    {
        return $this->request(
            'GET',
            self::API_BASE . '/CEP/Address/' . rawurlencode($cep),
            null,
            false,
            self::TIMEOUT_QUOTE
        );
    }

    /**
     * POST /tracking/trackinginfo (customer token only).
     *
     * @param string $serviceCode
     * @param string $trackingNumber
     * @return mixed[]
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
     * @return mixed[]
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
     * WhiteLabel configuration call: carriers, drop-off points and the default point of the store profile.
     *
     * Same two tokens, its own host. Validated live 2026-10-14: GET /carriers/services,
     * GET /carriers/{code}/zipcode/{cep}/branches (404 when the carrier has no drop-off points, e.g. Correios),
     * GET and PUT /profile/{code}/defaultbranch (404 when there is none yet; the PUT only works for Jadlog today,
     * the others answer 400 "Unidade não existe").
     *
     * @param string $method
     * @param string $path Path after /v1, e.g. "/carriers/services".
     * @param mixed $body
     * @return mixed[]
     * @throws LabelException With the HTTP status as code.
     */
    public function configuration(string $method, string $path, $body = null): array
    {
        return $this->request($method, $this->config->configurationUrl() . $path, $body, true, self::TIMEOUT_QUOTE * 2);
    }

    /**
     * Sends the request and decodes the JSON answer; a non-2xx answer becomes a LabelException.
     *
     * @param string $method
     * @param string $url
     * @param mixed $body
     * @param bool $partner
     * @param int $timeout
     * @param string $printingFormat
     * @return mixed[]
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
        $response = $this->send($method, $url, $this->options($body, $partner, $timeout, $printingFormat));
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $data = $this->decode($raw);
        if ($status < 200 || $status >= 300) {
            throw $this->failure($method, $url, $status, $data, $raw);
        }
        return $data;
    }

    /**
     * Guzzle options: tokens, printing format (partner calls), timeouts and the JSON body.
     *
     * @param mixed $body
     * @param bool $partner
     * @param int $timeout
     * @param string $printingFormat
     * @return mixed[]
     * @throws LabelException When the store token is missing.
     */
    private function options($body, bool $partner, int $timeout, string $printingFormat): array
    {
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
        return $options;
    }

    /**
     * The HTTP exchange itself; a transport failure (DNS, timeout) becomes a LabelException.
     *
     * @param string $method
     * @param string $url
     * @param mixed[] $options
     * @return ResponseInterface
     * @throws LabelException
     */
    private function send(string $method, string $url, array $options): ResponseInterface
    {
        try {
            /** @var HttpClient $client */
            $client = $this->clientFactory->create();
            return $client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            $this->logger->error('Frenet labels ' . $method . ' ' . $url . ': ' . $e->getMessage());
            /** @var Exception&GuzzleException $cause */
            $cause = $e;
            throw new LabelException(__('Frenet did not answer (%1).', $e->getMessage()), $cause);
        }
    }

    /**
     * Decoded JSON body; an empty or invalid body is an empty array (the HTTP status decides).
     *
     * @param string $raw
     * @return mixed[]
     */
    private function decode(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        try {
            return (array) $this->json->unserialize($raw);
        } catch (InvalidArgumentException $e) {
            return [];
        }
    }

    /**
     * Exception for a non-2xx answer: Frenet's message (plus the first detail), cleaned, with the HTTP status as code.
     *
     * @param string $method
     * @param string $url
     * @param int $status
     * @param mixed[] $data
     * @param string $raw
     * @return LabelException
     */
    private function failure(string $method, string $url, int $status, array $data, string $raw): LabelException
    {
        $message = (string) ($this->reader->field($data, 'message')
            ?? $this->reader->field($data, 'title') ?? ('HTTP ' . $status));
        $details = (array) ($this->reader->field($data, 'details') ?? []);
        if ($details && is_array($details[0] ?? null)) {
            $message .= ' ' . (string) ($this->reader->field($details[0], 'message') ?? '');
        }
        $this->logger->error(
            'Frenet labels ' . $method . ' ' . $url . ' HTTP ' . $status . ': '
            . $this->reader->redact(substr($raw, 0, 500))
        );
        return new LabelException(__('%1', $this->reader->cleanError(trim($message))), null, $status);
    }
}
