<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Config\Source\Labels;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options of the labels setting "PrintingFormat".
 */
class PrintingFormat implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'A4', 'label' => __('A4')],
            ['value' => '10x15', 'label' => __('10x15 (thermal printer)')],
        ];
    }
}
