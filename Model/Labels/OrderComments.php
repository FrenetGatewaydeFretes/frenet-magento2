<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Admin comments the labels leave on the order history.
 */
class OrderComments
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(private readonly OrderRepositoryInterface $orderRepository)
    {
    }

    /**
     * Adds a comment to the order and saves it.
     *
     * @param Order $order
     * @param string $text
     * @return void
     */
    public function add(Order $order, string $text): void
    {
        $order->addCommentToStatusHistory($text);
        $this->orderRepository->save($order);
    }

    /**
     * Loads the order, adds the comment and saves it.
     *
     * @param int $orderId
     * @param string $text
     * @return Order
     */
    public function addToOrderId(int $orderId, string $text): Order
    {
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);
        $this->add($order, $text);
        return $order;
    }
}
