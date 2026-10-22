<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\Labels\Dropoff as DropoffModel;
use Frenet\Shipping\Model\Labels\LabelException;
use Frenet\Shipping\Model\Labels\LabelsConfig;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * "Drop-off point": carriers of the store and the CEP the points are searched near (the store's origin).
 */
class Dropoff implements ArgumentInterface
{
    /** @var array{carriers: mixed[], error: string}|null */
    private ?array $data = null;

    /**
     * @param DropoffModel $dropoff
     * @param LabelsConfig $config
     */
    public function __construct(
        private readonly DropoffModel $dropoff,
        private readonly LabelsConfig $config
    ) {
    }

    /**
     * Carriers with an enabled service, or the error Frenet answered.
     *
     * @return array{carriers: mixed[], error: string}
     */
    public function data(): array
    {
        if ($this->data === null) {
            try {
                $this->data = ['carriers' => $this->dropoff->carriers(), 'error' => ''];
            } catch (LabelException $e) {
                $this->data = ['carriers' => [], 'error' => $e->getMessage()];
            }
        }
        return $this->data;
    }

    /**
     * CEP of the store's shipping origin, as 00000-000.
     *
     * @return string
     */
    public function originCep(): string
    {
        $cep = (string) preg_replace('/\D/', '', $this->config->storeValue('shipping/origin/postcode'));
        return strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep;
    }

    /**
     * Panel page of a carrier (where Frenet does accept every drop-off point).
     *
     * @param string $code
     * @return string
     */
    public function panelUrl(string $code): string
    {
        return $this->dropoff->panelUrl($code);
    }
}
