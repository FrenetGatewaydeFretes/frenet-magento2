<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\Labels\IssueStore;
use Frenet\Shipping\Model\Labels\LabelsConfig;
use Frenet\Shipping\Model\Labels\LabelStore;
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
     * @param IssueStore $issues
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LabelStore $store,
        private readonly LabelsConfig $config,
        private readonly IssueStore $issues
    ) {
    }

    /**
     * Why the last label of the order failed ("Notes"), or null.
     *
     * @return array{message: string, at: string}|null
     */
    public function issue(): ?array
    {
        return $this->orderId() ? $this->issues->get($this->orderId()) : null;
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
     * @return mixed[]|null
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
