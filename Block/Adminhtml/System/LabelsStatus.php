<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Block\Adminhtml\System;

use Frenet\Shipping\Model\Labels\LabelsConfig;
use Frenet\Shipping\Model\Labels\Wallet;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Read-only "Connection" line of the labels settings: whether both tokens work, and the wallet.
 */
class LabelsStatus extends Field
{
    /**
     * @param Context $context
     * @param LabelsConfig $config
     * @param Wallet $wallet
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly LabelsConfig $config,
        private readonly Wallet $wallet,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Renders the status box instead of an input.
     *
     * @param AbstractElement $element
     * @return string
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $escape = fn (string $value): string => (string) $this->escapeHtml($value);
        if (!$this->config->isAvailable()) {
            return '<div class="frenet-status frenet-status--off">'
                . $escape((string) __('Paste the partner token and save: the connection and the balance appear here.'))
                . '</div>';
        }
        $wallet = $this->wallet->refresh();
        if ($wallet === null) {
            return '<div class="frenet-status frenet-status--bad">'
                . $escape((string) __(
                    'Frenet did not accept the tokens or did not answer. Check the partner token and the Frenet token.'
                ))
                . '</div>';
        }
        return '<div class="frenet-status frenet-status--ok">'
            . '<strong>' . $escape((string) __('Connected to Frenet')) . '</strong>'
            . '<span>' . $escape((string) __(
                'Balance: R$ %1 · %2 labels available',
                number_format($wallet['balance'], 2, ',', '.'),
                $wallet['labels']
            )) . '</span></div>';
    }
}
