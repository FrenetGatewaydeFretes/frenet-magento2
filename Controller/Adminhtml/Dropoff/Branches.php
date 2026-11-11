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
 * Drop-off points of a carrier near a CEP, with its current default point (JSON).
 */
class Branches extends Action implements HttpPostActionInterface
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
     * Answers {ok, branches, default} or {ok: false, message}.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $carrier = (string) $this->getRequest()->getParam('carrier');
        try {
            $branches = $this->dropoff->branches($carrier, (string) $this->getRequest()->getParam('cep'));
        } catch (LabelException $e) {
            return $this->jsonFactory->create()->setData(['ok' => false, 'message' => $e->getMessage()]);
        }
        try {
            $default = $this->dropoff->defaultBranch($carrier);
        } catch (LabelException $e) {
            $default = null;
        }
        return $this->jsonFactory->create()->setData(['ok' => true, 'branches' => $branches, 'default' => $default]);
    }
}
