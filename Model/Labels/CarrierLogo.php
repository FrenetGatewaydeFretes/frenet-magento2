<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Logo of a carrier on the labels screens.
 *
 * Frenet's API sends no logo, so the official files are bundled in view/adminhtml/web/images/carriers, one per Frenet
 * carrier code (Correios, Jadlog, J&T Express, Loggi, Total Express, LATAM Cargo, Azul Cargo Express). A carrier
 * without a file gets a monogram in its brand colour, so a new carrier never breaks the layout.
 */
class CarrierLogo
{
    private const DIR = 'Frenet_Shipping::images/carriers/';

    /** Frenet carrier code => bundled file. */
    private const FILES = [
        'COR' => 'cor.svg',
        'JAD' => 'jad.png',
        'JTE' => 'jte.svg',
        'LOG' => 'log.png',
        'TOT' => 'tot.svg',
        'LAT' => 'lat.svg',
        'AZU' => 'azu.png',
    ];

    /** Brand colour of the monogram, for carriers whose file is missing or that have none. */
    private const COLORS = [
        'COR' => '#005ca9',
        'JAD' => '#e4002b',
        'JTE' => '#d7262d',
        'LOG' => '#00a9e0',
        'TOT' => '#22315e',
        'LAT' => '#1b0088',
        'AZU' => '#0d2a5c',
        'BUS' => '#1f8a4c',
        'SEQ' => '#0e7c6b',
    ];

    /** Lower-case needle found in the carrier name (or in an odd code) => known code. */
    private const NAMES = [
        'correios' => 'COR',
        'jadlog' => 'JAD',
        'j&t' => 'JTE',
        'jt exp' => 'JTE',
        'loggi' => 'LOG',
        'total' => 'TOT',
        'latam' => 'LAT',
        'azul' => 'AZU',
        'buslog' => 'BUS',
        'sequoia' => 'SEQ',
    ];

    /** Monogram colours for carriers nobody listed above (picked by the code, so it is stable). */
    private const PALETTE = ['#0c005a', '#7b3ff3', '#0b6b47', '#7a4b00', '#a4161a', '#344054'];

    /**
     * Known carrier code for a Frenet code and name ("JTE" for "J&T Express", also when the code is odd).
     *
     * @param string $code Frenet carrier code.
     * @param string $name Carrier name.
     * @return string
     */
    public function code(string $code, string $name = ''): string
    {
        $code = strtoupper(trim($code));
        if ($code !== '' && isset(self::FILES[$code])) {
            return $code;
        }
        $lower = mb_strtolower($name . ' ' . $code);
        foreach (self::NAMES as $needle => $known) {
            if (str_contains($lower, $needle)) {
                return $known;
            }
        }
        return $code;
    }

    /**
     * Asset id of the bundled logo ("Frenet_Shipping::images/carriers/cor.svg"), or null when the carrier has none.
     *
     * @param string $code Known code (see code()).
     * @return string|null
     */
    public function file(string $code): ?string
    {
        return isset(self::FILES[$code]) ? self::DIR . self::FILES[$code] : null;
    }

    /**
     * Colour of the monogram.
     *
     * @param string $code Known code.
     * @return string
     */
    public function color(string $code): string
    {
        if (isset(self::COLORS[$code])) {
            return self::COLORS[$code];
        }
        $sum = array_sum(array_map('ord', str_split($code !== '' ? $code : '?')));
        return self::PALETTE[$sum % count(self::PALETTE)];
    }

    /**
     * Two letters for the monogram: initials of the name ("Nova Transportadora" => "NT"), else the start of the code.
     *
     * @param string $name Carrier name.
     * @param string $code Known code.
     * @return string
     */
    public function initials(string $name, string $code): string
    {
        $words = preg_split('/[\s\-_]+/u', trim($name));
        $words = array_values(array_filter(
            is_array($words) ? $words : [],
            static fn ($word): bool => preg_replace('/[^\p{L}\p{N}]/u', '', $word) !== ''
        ));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
        }
        $base = trim($name) !== '' ? $name : $code;
        return mb_strtoupper(mb_substr((string) preg_replace('/[^\p{L}\p{N}]/u', '', $base), 0, 2));
    }

    /**
     * Asset id of every bundled logo, by carrier code.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        $out = [];
        foreach (array_keys(self::FILES) as $code) {
            $out[$code] = (string) $this->file($code);
        }
        return $out;
    }

    /**
     * Brand colours by carrier code.
     *
     * @return array<string, string>
     */
    public function colors(): array
    {
        return self::COLORS;
    }

    /**
     * Name needles => known code.
     *
     * @return array<string, string>
     */
    public function names(): array
    {
        return self::NAMES;
    }

    /**
     * Monogram colours for unknown carriers.
     *
     * @return string[]
     */
    public function palette(): array
    {
        return self::PALETTE;
    }
}
