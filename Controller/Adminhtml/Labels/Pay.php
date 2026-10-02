<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Labels;

use Frenet\Shipping\Model\Labels\LabelException;
use Frenet\Shipping\Model\Labels\ShipmentService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Pays the selected unpaid labels with the Frenet wallet ("cart").
 */
class Pay extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::labels';

    /**
     * @param Context $context
     * @param ShipmentService $shipments
     */
    public function __construct(Context $context, private readonly ShipmentService $shipments)
    {
        parent::__construct($context);
    }

    /**
     * Pays and reports what Frenet really charged.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $ids = (array) $this->getRequest()->getParam('ids', []);
        if (!$ids) {
            $this->messageManager->addErrorMessage(__('Select the labels to pay.'));
        } else {
            try {
                $r = $this->shipments->pay($ids);
                if ($r['paid']) {
                    $this->messageManager->addSuccessMessage(__('%1 label(s) paid.', count($r['paid'])));
                }
                if ($r['unpaid']) {
                    $this->messageManager->addErrorMessage(__('%1 label(s) not paid: %2', count($r['unpaid']), $r['message']));
                }
            } catch (LabelException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
