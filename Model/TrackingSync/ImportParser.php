<?php
/**
 * Frenet Shipping Gateway — parses pasted lines "order number <sep> tracking code" (sep: ; , tab or spaces).
 * Blank lines and a header row are skipped. Pure function (unit tested).
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\TrackingSync;

class ImportParser
{
    /**
     * Valid rows (deduplicated by order + code) and the numbers of the lines that could not be read.
     *
     * @param string $text
     * @return array{rows: array<int, array{order: string, code: string}>, invalid: array<int, int>}
     */
    public static function parse(string $text): array
    {
        $rows = [];
        $invalid = [];
        foreach (preg_split('/\R/', $text) ?: [] as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_values(array_filter(array_map('trim', preg_split('/[;,\t ]+/', $line) ?: []), 'strlen'));
            if (count($parts) < 2) {
                $invalid[] = $i + 1;
                continue;
            }
            $order = ltrim($parts[0], '#');
            $code = strtoupper($parts[1]);
            // Real tracking codes always have digits; a header like "pedido;codigo" does not.
            if (!preg_match('/^[A-Za-z0-9-]+$/', $order) || !preg_match('/^[A-Z0-9-]{6,40}$/', $code) || !preg_match('/\d/', $code)) {
                if ($i === 0) {
                    continue;
                }
                $invalid[] = $i + 1;
                continue;
            }
            $rows[$order . '|' . $code] = ['order' => $order, 'code' => $code];
        }
        return ['rows' => array_values($rows), 'invalid' => $invalid];
    }
}
