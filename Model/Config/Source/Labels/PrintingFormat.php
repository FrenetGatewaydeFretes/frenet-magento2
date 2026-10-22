<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Config\Source\Labels;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Options of the labels setting "PrintingFormat".
 */
class PrintingFormat implements OptionSourceInterface
{
    /**
     * Returns the selectable options of the labels printing format setting.
     *
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'A4', 'label' => __('A4')],
            ['value' => '10x15', 'label' => __('10x15 (thermal printer)')],
        ];
    }
}
