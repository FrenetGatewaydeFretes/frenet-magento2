<?php
/**
 * Frenet Shipping Gateway — "Update now": reads the Frenet events (all due codes, or one order).
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Tracking;

use Frenet\Shipping\Model\TrackingSync\Sync as TrackingSync;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Sync extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::tracking';

    /**
     * @param Context $context
     * @param TrackingSync $sync
     */
    public function __construct(Context $context, private readonly TrackingSync $sync)
    {
        parent::__construct($context);
    }

    /**
     * Runs the check and tells what happened.
     *
     * @return Redirect
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function execute(): Redirect
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $result = $this->sync->run($orderId ?: null);
        if ($result['synced'] === 0 && $result['errors'] === 0) {
            $this->messageManager->addNoticeMessage(__('No Frenet tracking code to check right now.'));
        } elseif ($result['errors'] > 0 && $result['synced'] === 0) {
            $this->messageManager->addErrorMessage(
                __('Frenet did not answer the tracking of %1 code(s). Try again in a few minutes.', $result['errors'])
            );
        } else {
            $this->messageManager->addSuccessMessage(
                __('%1 code(s) checked, %2 order status(es) changed.', $result['synced'], $result['changed'])
            );
            if ($result['errors']) {
                $this->messageManager->addWarningMessage(
                    __('%1 code(s) did not answer and will be checked again later.', $result['errors'])
                );
            }
        }
        $redirect = $this->resultRedirectFactory->create();
        return $orderId
            ? $redirect->setPath('sales/order/view', ['order_id' => $orderId])
            : $redirect->setPath('frenetshipping/tracking/index');
    }
}
