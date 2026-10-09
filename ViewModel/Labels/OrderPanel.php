<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\Labels\LabelStore;
use Frenet\Shipping\Model\Labels\LabelsConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Frenet box on the admin order view: the shipment of the order, if any, and what can be done with it.
 */
class OrderPanel implements ArgumentInterface
{
    /**
     * @param RequestInterface $request
     * @param LabelStore $store
     * @param LabelsConfig $config
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LabelStore $store,
        private readonly LabelsConfig $config
    ) {
    }

    /**
     * Order id of the page.
     *
     * @return int
     */
    public function orderId(): int
    {
        return (int) $this->request->getParam('order_id');
    }

    /**
     * Frenet shipment of the order that still exists, or null.
     *
     * @return array|null
     */
    public function shipment(): ?array
    {
        return $this->orderId() ? $this->store->activeForOrder($this->orderId()) : null;
    }

    /**
     * Labels feature on and configured.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->config->isAvailable();
    }
}
