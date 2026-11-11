<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Framework\FlagManager;

/**
 * Carriers that refuse a shipment without an NF-e key, learned from Frenet's own refusals (QA 2026-10-15: J&T and
 * Jadlog). "Create shipment" marks their services "requires NF-e" and never proposes them by default to an order
 * without a key.
 */
class NfeCarriers
{
    private const FLAG = 'frenet_shipping_labels_nfe_carriers';

    /**
     * @param FlagManager $flags
     */
    public function __construct(private readonly FlagManager $flags)
    {
    }

    /**
     * Carrier codes (upper case) that need an NF-e key.
     *
     * @return string[]
     */
    public function all(): array
    {
        return array_values(array_filter(array_map('strval', (array) $this->flags->getFlagData(self::FLAG))));
    }

    /**
     * Frenet refused a shipment of this carrier for lack of an NF-e key: remember it for the next quotes.
     *
     * @param string $carrierCode
     * @return void
     */
    public function remember(string $carrierCode): void
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9_]/', '', $carrierCode));
        $known = $this->all();
        if ($code !== '' && !in_array($code, $known, true)) {
            $known[] = $code;
            $this->flags->saveFlag(self::FLAG, $known);
        }
    }

    /**
     * Whether a Frenet error is about the invoice (NF-e) of the order.
     *
     * @param string $message Raw Frenet message.
     * @return bool
     */
    public function isInvoiceError(string $message): bool
    {
        $lower = mb_strtolower($message);
        return str_contains($lower, 'nota fiscal')
            || str_contains($lower, 'chave de acesso')
            || str_contains($lower, 'nf-e');
    }
}
