<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Plugin\Labels;

use Frenet\Shipping\Model\Labels\LabelStore;
use Frenet\Shipping\Model\Labels\LabelsConfig;
use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Block\Adminhtml\Order\View;

/**
 * Order toolbar: "Print Frenet label" when the order has one, otherwise "Create Frenet shipment".
 */
class OrderViewButtons
{
    /**
     * @param LabelStore $store
     * @param LabelsConfig $config
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly LabelStore $store,
        private readonly LabelsConfig $config,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * Adds the button before the toolbar renders.
     *
     * @param View $subject
     * @return null
     */
    public function beforeSetLayout(View $subject)
    {
        if (!$this->config->isAvailable()) {
            return null;
        }
        $orderId = (int) $subject->getRequest()->getParam('order_id');
        $shipment = $orderId ? $this->store->activeForOrder($orderId) : null;
        $flags = JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES;
        if ($shipment && !empty($shipment['label_url'])) {
            $subject->addButton('frenet_label_print', [
                'label' => __('Print Frenet label'),
                'class' => 'frenet-button',
                'onclick' => sprintf("window.open(%s, '_blank', 'noopener')", json_encode((string) $shipment['label_url'], $flags)),
            ], 0, 5);
        } elseif (!$shipment && $this->authorization->isAllowed('Frenet_Shipping::labels_create')) {
            $subject->addButton('frenet_label_create', [
                'label' => __('Create Frenet shipment'),
                'class' => 'frenet-button',
                'onclick' => sprintf(
                    'setLocation(%s)',
                    json_encode($subject->getUrl('frenetshipping/shipment/index', ['ids' => $orderId]), $flags)
                ),
            ], 0, 5);
        }
        return null;
    }
}
