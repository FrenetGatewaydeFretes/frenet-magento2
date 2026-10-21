<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * One PDF with the labels of paid shipments (POST /shipments/batch/label/generate).
 */
class LabelPrinter
{
    /**
     * @param Client $client
     * @param ResponseReader $reader
     */
    public function __construct(
        private readonly Client $client,
        private readonly ResponseReader $reader
    ) {
    }

    /**
     * The PDF, with how many of the labels Frenet could not print.
     *
     * @param string[] $shipmentIds
     * @param string $format A4 or 10x15.
     * @return array{pdf: string, failed: int}
     * @throws LabelException
     */
    public function batchPdf(array $shipmentIds, string $format): array
    {
        $ids = array_map('intval', array_values(array_filter(array_map('strval', $shipmentIds), 'ctype_digit')));
        if (!$ids) {
            throw new LabelException(__('Select at least one paid label.'));
        }
        $res = $this->client->whitelabel('POST', '/shipments/batch/label/generate', $ids, $format);
        $document = (string) ($this->reader->field($res, 'document') ?? '');
        // Frenet returns the PDF as base64 in "document".
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $pdf = $document !== '' ? (string) base64_decode($document, true) : '';
        if ($pdf === '') {
            throw new LabelException(__('Frenet did not generate the PDF. Only paid labels can be printed.'));
        }
        // Frenet repeats the item of a label it cannot print, so failures are counted per shipment.
        $failed = [];
        foreach ((array) ($this->reader->field($res, 'items') ?? []) as $item) {
            if (!empty($this->reader->field((array) $item, 'errors'))) {
                $failed[(string) $this->reader->field((array) $item, 'shipmentId')] = true;
            }
        }
        return ['pdf' => $pdf, 'failed' => count($failed)];
    }
}
