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
 * Options of the labels setting "Journey".
 */
class Journey implements OptionSourceInterface
{
    /**
     * Returns the selectable options of the labels journey setting.
     *
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'here', 'label' => __('Buy here (pay with the Frenet wallet)')],
            ['value' => 'panel', 'label' => __('Send to the Frenet panel')],
        ];
    }
}
