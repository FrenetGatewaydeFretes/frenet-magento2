<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Magento address to Frenet address. Brazilian stores use 2, 3 or 4 street lines:
 *   4: street, number, complement, district · 3: street, number, district · 2: street (with number), district.
 * Frenet rejects an empty number ("Numero do destinatário inválido"), so with 2 lines a bare number in the second
 * line is the number, and otherwise the number is split off the end of the street ("Rua X, 123", "Rua X nº 123").
 */
class AddressMapper
{
    /** A street line that holds only the house number ("123", "12A", "S/N"). */
    private const NUMBER = '/^(?:n[º°o.]?\s*)?(\d+[A-Za-z]?|s\/?n)$/iu';

    /**
     * Maps Magento street lines to the Frenet address fields.
     *
     * @param string[] $street Street lines as stored by Magento.
     * @param string $city
     * @param string $regionCode
     * @param string $postcode
     * @return array<string, string>
     */
    public static function toFrenet(array $street, string $city, string $regionCode, string $postcode): array
    {
        $lines = array_values(array_map('trim', array_map('strval', $street)));
        $count = count($lines);
        $out = ['Street' => $lines[0] ?? '', 'AddressNumber' => '', 'AddressComplement' => '', 'AddressQuarter' => ''];
        if ($count >= 4) {
            $out['AddressNumber'] = $lines[1];
            $out['AddressComplement'] = $lines[2];
            $out['AddressQuarter'] = $lines[3];
        } elseif ($count === 3) {
            $out['AddressNumber'] = $lines[1];
            $out['AddressQuarter'] = $lines[2];
        } elseif ($count === 2 && preg_match(self::NUMBER, $lines[1], $n)) {
            $out['AddressNumber'] = $n[1];
        } elseif ($count === 2) {
            $out['AddressQuarter'] = $lines[1];
        }
        if ($out['AddressNumber'] === '' && preg_match('/^(.+?)[\s,]+(?:n[º°o.]?\s*)?(\d+[A-Za-z]?|s\/?n)\s*$/iu', $out['Street'], $m)) {
            $out['Street'] = rtrim($m[1], ' ,');
            $out['AddressNumber'] = $m[2];
        }
        if ($out['AddressQuarter'] === '') {
            $out['AddressQuarter'] = '-';
        }
        return $out + [
            'City' => $city,
            'AddressState' => strtoupper($regionCode),
            'ZipCode' => (string) preg_replace('/\D/', '', $postcode),
            'Country' => 'BR',
        ];
    }
}
