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
 * Options of the labels setting "DefaultService".
 */
class DefaultService implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'price', 'label' => __('Best price')],
            ['value' => 'time', 'label' => __('Best delivery time')],
        ];
    }
}
