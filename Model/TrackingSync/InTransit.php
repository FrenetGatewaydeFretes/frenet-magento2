<?php
/**
 * Frenet Shipping Gateway — moves the order to "Em transporte" when a Frenet tracking code is added
 * (only with "Change the order status automatically" on).
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\TrackingSync;

use Frenet\Shipping\Model\Extras\Settings;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class InTransit
{
    /**
     * @param Settings $settings
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private readonly Settings $settings,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Applies the status when it makes sense; never moves an order back from a later status.
     *
     * @param int $orderId
     * @param string $trackNumber
     * @return bool Status changed.
     */
    public function apply(int $orderId, string $trackNumber): bool
    {
        if (!$this->settings->autoStatus()) {
            return false;
        }
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);
        if (isset(StatusMapper::LABELS[(string) $order->getStatus()])
            || !in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            return false;
        }
        $order->addCommentToStatusHistory(
            (string) __('Frenet tracking code added: %1', $trackNumber),
            StatusMapper::IN_TRANSIT,
            true
        );
        $this->orderRepository->save($order);
        return true;
    }
}
