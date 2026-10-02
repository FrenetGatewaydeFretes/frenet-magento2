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
 * Cancels one Frenet label (paid: refund to the wallet; unpaid: deleted), confirmed by its real status.
 */
class Cancel extends Action implements HttpPostActionInterface
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
     * Cancels and goes back.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $this->messageManager->addSuccessMessage($this->shipments->cancel((string) $this->getRequest()->getParam('id')));
        } catch (LabelException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
