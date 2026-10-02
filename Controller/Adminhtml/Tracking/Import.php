<?php
/**
 * Frenet Shipping Gateway — bulk tracking codes in two steps: preview (nothing changes) and confirm.
 * The preview lives in the admin session, so reloading the page never applies it twice.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Tracking;

use Frenet\Shipping\Model\TrackingSync\Importer;
use Frenet\Shipping\Model\TrackingSync\ImportParser;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Import extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::tracking';
    public const SESSION_KEY = 'frenet_tracking_import';
    private const MAX_ROWS = 500;

    /**
     * @param Context $context
     * @param Importer $importer
     */
    public function __construct(Context $context, private readonly Importer $importer)
    {
        parent::__construct($context);
    }

    /**
     * step=preview | confirm | cancel.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $step = (string) $this->getRequest()->getParam('step');
        $redirect = $this->resultRedirectFactory->create()->setPath('frenetshipping/tracking/index', ['_fragment' => 'frenet-import']);
        $session = $this->_session;
        if ($step === 'cancel') {
            $session->unsetData(self::SESSION_KEY);
            return $redirect;
        }
        if ($step === 'confirm') {
            $saved = (array) $session->getData(self::SESSION_KEY);
            $session->unsetData(self::SESSION_KEY);
            if (empty($saved['rows'])) {
                $this->messageManager->addNoticeMessage(__('Nothing to import. Paste the codes again.'));
                return $redirect;
            }
            $r = $this->importer->apply($saved['rows'], (bool) ($saved['notify'] ?? false));
            $this->messageManager->addSuccessMessage(__(
                '%1 order(s) shipped with the code, %2 code(s) added to existing shipments, %3 skipped.',
                $r['shipped'],
                $r['added'],
                $r['skipped']
            ));
            foreach (array_slice($r['errors'], 0, 10) as $error) {
                $this->messageManager->addErrorMessage($error);
            }
            return $redirect;
        }
        $raw = (string) $this->getRequest()->getParam('lines');
        $parsed = ImportParser::parse($raw);
        if (!$parsed['rows']) {
            $this->messageManager->addErrorMessage(__('No line could be read. Use one order per line: order number and tracking code, separated by ";", "," or a tab.'));
            $session->setData(self::SESSION_KEY, ['raw' => $raw, 'rows' => [], 'preview' => [], 'invalid' => $parsed['invalid'], 'notify' => false]);
            return $redirect;
        }
        $rows = array_slice($parsed['rows'], 0, self::MAX_ROWS);
        $session->setData(self::SESSION_KEY, [
            'raw' => $raw,
            'rows' => $rows,
            'preview' => $this->importer->preview($rows),
            'invalid' => $parsed['invalid'],
            'truncated' => count($parsed['rows']) > self::MAX_ROWS,
            'notify' => (bool) $this->getRequest()->getParam('notify'),
        ]);
        return $redirect;
    }
}
