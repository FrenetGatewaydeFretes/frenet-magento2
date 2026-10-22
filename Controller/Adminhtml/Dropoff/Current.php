<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Dropoff;

use Frenet\Shipping\Model\Labels\Dropoff;
use Frenet\Shipping\Model\Labels\LabelException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Current default drop-off point of a carrier (JSON); the overview loads one per carrier.
 */
class Current extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::dropoff';

    /**
     * @param Context $context
     * @param Dropoff $dropoff
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly Dropoff $dropoff,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Answers {ok, default} or {ok: false, message}.
     *
     * @return Json
     */
    public function execute(): Json
    {
        try {
            $default = $this->dropoff->defaultBranch((string) $this->getRequest()->getParam('carrier'));
        } catch (LabelException $e) {
            return $this->jsonFactory->create()->setData(['ok' => false, 'message' => $e->getMessage()]);
        }
        return $this->jsonFactory->create()->setData(['ok' => true, 'default' => $default]);
    }
}
