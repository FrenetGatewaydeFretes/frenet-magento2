<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Labels;

use Frenet\Shipping\Model\Labels\LabelException;
use Frenet\Shipping\Model\Labels\LabelsConfig;
use Frenet\Shipping\Model\Labels\ShipmentService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * One PDF with the selected paid labels (Frenet batch generation), in A4 or 10x15.
 */
class PrintPdf extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::print';

    /**
     * @param Context $context
     * @param ShipmentService $shipments
     * @param LabelsConfig $config
     * @param RawFactory $rawFactory
     */
    public function __construct(
        Context $context,
        private readonly ShipmentService $shipments,
        private readonly LabelsConfig $config,
        private readonly RawFactory $rawFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Streams the PDF inline (the browser opens it to print).
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $format = $this->getRequest()->getParam('format') === '10x15' ? '10x15' : (
            $this->getRequest()->getParam('format') === 'A4' ? 'A4' : $this->config->printingFormat()
        );
        try {
            $r = $this->shipments->batchPdf((array) $this->getRequest()->getParam('ids', []), $format);
        } catch (LabelException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
        }
        /** @var Raw $raw */
        $raw = $this->rawFactory->create();
        return $raw->setHeader('Content-Type', 'application/pdf', true)
            ->setHeader('Content-Disposition', 'inline; filename="etiquetas-frenet-' . date('Ymd-His') . '.pdf"', true)
            ->setHeader('Cache-Control', 'private, no-store', true)
            ->setContents($r['pdf']);
    }
}
