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
     */
    public function execute(): Redirect
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $r = $this->sync->run($orderId ?: null);
        if ($r['synced'] === 0 && $r['errors'] === 0) {
            $this->messageManager->addNoticeMessage(__('No Frenet tracking code to check right now.'));
        } elseif ($r['errors'] > 0 && $r['synced'] === 0) {
            $this->messageManager->addErrorMessage(__('Frenet did not answer the tracking of %1 code(s). Try again in a few minutes.', $r['errors']));
        } else {
            $this->messageManager->addSuccessMessage(__('%1 code(s) checked, %2 order status(es) changed.', $r['synced'], $r['changed']));
            if ($r['errors']) {
                $this->messageManager->addWarningMessage(__('%1 code(s) did not answer and will be checked again later.', $r['errors']));
            }
        }
        $redirect = $this->resultRedirectFactory->create();
        return $orderId
            ? $redirect->setPath('sales/order/view', ['order_id' => $orderId])
            : $redirect->setPath('frenetshipping/tracking/index');
    }
}
