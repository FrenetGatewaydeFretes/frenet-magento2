<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Dropoff;

use Frenet\Shipping\Model\Labels\Dropoff;
use Frenet\Shipping\Model\Labels\DropoffRefusedException;
use Frenet\Shipping\Model\Labels\LabelException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Saves the default drop-off point of a carrier on the Frenet profile, then reads it back (JSON).
 */
class Save extends Action implements HttpPostActionInterface
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
     * Answers {ok, default, message} or {ok: false, message, panel}.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $carrier = (string) $this->getRequest()->getParam('carrier');
        try {
            $this->dropoff->setDefault($carrier, (string) $this->getRequest()->getParam('id'));
        } catch (LabelException $e) {
            return $this->jsonFactory->create()->setData([
                'ok' => false,
                'message' => $e->getMessage(),
                // Frenet refuses the point by API for some carriers: offer the panel page of that carrier.
                'panel' => $e instanceof DropoffRefusedException ? $this->dropoff->panelUrl($carrier) : '',
            ]);
        }
        try {
            // Read it back: the profile is what Frenet uses for the next labels.
            $default = $this->dropoff->defaultBranch($carrier);
        } catch (LabelException $e) {
            $default = null;
        }
        return $this->jsonFactory->create()->setData([
            'ok' => true,
            'default' => $default,
            'message' => (string) __(
                'Default drop-off point saved. Frenet uses it for the next labels of this carrier.'
            ),
        ]);
    }
}
