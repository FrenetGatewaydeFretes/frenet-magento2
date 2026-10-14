<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * NF-e access key (44 digits): cUF(2) AAMM(4) CNPJ(14) model(2) series(3) number(9) tpEmis(1) cNF(8) cDV(1).
 * The last digit is a module 11 check digit (weights 2..9 from right to left).
 */
class NfeKey
{
    /**
     * Keeps only the digits of a pasted key ("3526 0911 ..." or with dots).
     *
     * @param string $key
     * @return string
     */
    public static function normalize(string $key): string
    {
        return (string) preg_replace('/\D/', '', $key);
    }

    /**
     * Whether the key has 44 digits and a correct check digit.
     *
     * @param string $key
     * @return bool
     */
    public static function isValid(string $key): bool
    {
        $key = self::normalize($key);
        if (strlen($key) !== 44) {
            return false;
        }
        return self::checkDigit(substr($key, 0, 43)) === (int) $key[43];
    }

    /**
     * Module 11 check digit of the first 43 digits.
     *
     * @param string $digits43
     * @return int
     */
    public static function checkDigit(string $digits43): int
    {
        $sum = 0;
        $weight = 2;
        for ($i = strlen($digits43) - 1; $i >= 0; $i--) {
            $sum += (int) $digits43[$i] * $weight;
            $weight = $weight === 9 ? 2 : $weight + 1;
        }
        $rest = $sum % 11;
        return $rest < 2 ? 0 : 11 - $rest;
    }

    /**
     * Number, series and issuer CNPJ read from the key.
     *
     * @param string $key
     * @return array{number: string, series: string, cnpj: string, model: string}
     */
    public static function parse(string $key): array
    {
        $key = self::normalize($key);
        return [
            'number' => ltrim(substr($key, 25, 9), '0') ?: '0',
            'series' => ltrim(substr($key, 22, 3), '0') ?: '0',
            'cnpj' => substr($key, 6, 14),
            'model' => substr($key, 20, 2),
        ];
    }
}
