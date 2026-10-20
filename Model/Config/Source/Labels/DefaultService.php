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
 * Options of the labels setting "DefaultService".
 */
class DefaultService implements OptionSourceInterface
{
    /**
     * Returns the selectable options of the labels default service setting.
     *
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'price', 'label' => __('Best price')],
            ['value' => 'time', 'label' => __('Best delivery time')],
        ];
    }
}
