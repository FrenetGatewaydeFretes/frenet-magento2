<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Shapes of the drop-off point answers (GET /carriers/services, /branches, /defaultbranch). Unit tested.
 */
class DropoffPoints
{
    /**
     * @param ResponseReader $reader
     */
    public function __construct(private readonly ResponseReader $reader)
    {
    }

    /**
     * Groups the services by carrier, carriers sorted by name.
     *
     * @param mixed[] $services shippingServices.
     * @return array<int, array{code: string, name: string, services: string[]}>
     */
    public function group(array $services): array
    {
        $byCarrier = [];
        foreach ($services as $service) {
            if (!is_array($service) || ($this->reader->field($service, 'enabled') ?? true) === false) {
                continue;
            }
            $code = (string) ($this->reader->field($service, 'carrierCode') ?? '');
            if ($code === '') {
                continue;
            }
            if (!isset($byCarrier[$code])) {
                $name = (string) ($this->reader->field($service, 'carrier') ?? '');
                $byCarrier[$code] = ['code' => $code, 'name' => $name !== '' ? $name : $code, 'services' => []];
            }
            $byCarrier[$code]['services'][] = (string) ($this->reader->field($service, 'serviceDescription')
                ?? $this->reader->field($service, 'serviceCode') ?? '');
        }
        $out = array_values($byCarrier);
        usort($out, static fn ($left, $right) => strcasecmp($left['name'], $right['name']));
        return $out;
    }

    /**
     * One point as the screen needs it.
     *
     * @param mixed[] $raw Raw branch (camelCase or PascalCase).
     * @return array{id: string, name: string, initials: string, address: string, type: string}
     */
    public function branch(array $raw): array
    {
        $address = (array) ($this->reader->field($raw, 'address') ?? []);
        $get = fn (string $key): string => trim((string) ($this->reader->field($address, $key) ?? ''));
        $cep = (string) preg_replace('/\D/', '', $get('zipCode'));
        // Frenet may drop the leading zero of the CEP.
        $cep = $cep !== '' ? str_pad($cep, 8, '0', STR_PAD_LEFT) : '';
        $place = trim($get('city') . ($get('addressState') !== '' ? '/' . $get('addressState') : ''), '/');
        $parts = array_filter([
            $get('address'),
            $get('addressQuarter'),
            $place,
            $cep !== '' ? 'CEP ' . substr($cep, 0, 5) . '-' . substr($cep, 5) : '',
        ]);
        return [
            'id' => (string) ($this->reader->field($raw, 'id') ?? ''),
            'name' => trim((string) ($this->reader->field($raw, 'nickName') ?? '')),
            'initials' => (string) ($this->reader->field($raw, 'initials') ?? ''),
            'address' => implode(' · ', $parts),
            'type' => (string) ($this->reader->field($raw, 'type') ?? ''),
        ];
    }

    /**
     * Carrier code as Frenet expects it (letters and digits, upper case).
     *
     * @param string $carrier
     * @return string
     */
    public function code(string $carrier): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9_]/', '', $carrier));
    }
}
