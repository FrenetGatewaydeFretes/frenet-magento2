<?php
/**
 * Frenet_Shipping
 *
 * @vendor    Frenet
 * @package   Shipping
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@frenet.com.br>
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Offers the API transport choices for the carrier config, with an empty option meaning "keep the frenet-php default".
 */
class ApiProtocol implements OptionSourceInterface
{
    /**
     * @var string
     */
    public const HTTPS = 'https';

    /**
     * @var string
     */
    public const HTTP = 'http';

    /**
     * Returns the selectable protocol options for the carrier configuration field.
     *
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Default')],
            ['value' => self::HTTPS, 'label' => __('HTTPS')],
            ['value' => self::HTTP, 'label' => __('HTTP')],
        ];
    }
}
