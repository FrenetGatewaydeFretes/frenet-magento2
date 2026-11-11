<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Reads the WhiteLabel answers and formats their errors. Injected wherever a Frenet answer is interpreted, so
 * Client only talks HTTP.
 */
class ResponseReader
{
    /**
     * Reads $key in camelCase or PascalCase (WhiteLabel answers camelCase, the docs show PascalCase).
     *
     * @param mixed[] $data
     * @param int|string $key
     * @return mixed
     */
    public function field(array $data, $key)
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
    public function redact(string $text): string
    {
        return (string) preg_replace('/([?&]p=)[^&"\s]+/', '$1***', $text);
    }

    /**
     * Keeps only the human part of a Frenet error and explains the known ones.
     *
     * @param string $message
     * @return string
     */
    public function cleanError(string $message): string
    {
        if (stripos($message, 'acesso negado para o parceiro') !== false) {
            return (string) __(
                'Frenet refused the partner token. Check the partner token in the labels settings. '
                . 'Nothing was charged.'
            );
        }
        if (stripos($message, 'acesso negado para o cliente') !== false) {
            return (string) __('Frenet refused the customer token. Check the Frenet token. Nothing was charged.');
        }
        if (stripos($message, 'nota fiscal') !== false) {
            return (string) __(
                'This service requires an invoice (NF-e). Paste the NF-e key of the order and try again. '
                . 'Nothing was charged.'
            );
        }
        $message = (string) preg_replace('/\s*-->.*$/s', '', $message);
        $message = (string) preg_replace('/(Detalhes )?identificador:\s*[0-9a-f-]{36}\s*/i', '', $message);
        return trim($message);
    }
}
