<?php
/**
 * Frenet Shipping Gateway — "Shipment tracking": every Frenet tracking code of the store with its latest
 * event (read in the background), the timeline of one code, and the bulk import of codes.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Controller\Adminhtml\Tracking\Import;
use Frenet\Shipping\Model\Extras\Settings;
use Frenet\Shipping\Model\TrackingSync\Importer;
use Frenet\Shipping\Model\TrackingSync\StatusMapper;
use Frenet\Shipping\Model\TrackingSync\Sync;
use Magento\Backend\Model\Session;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as StatusCollectionFactory;

class Tracking implements ArgumentInterface
{
    public const PER_PAGE = 30;

    /** @var array|null */
    private ?array $overview = null;

    /** @var array<string, string>|null */
    private ?array $statusLabels = null;

    /**
     * @param RequestInterface $request
     * @param Sync $sync
     * @param Settings $settings
     * @param Session $session
     * @param UrlInterface $url
     * @param StatusCollectionFactory $statusCollection
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Sync $sync,
        private readonly Settings $settings,
        private readonly Session $session,
        private readonly UrlInterface $url,
        private readonly StatusCollectionFactory $statusCollection
    ) {
    }

    /**
     * Status filter from the URL.
     *
     * @return string
     */
    public function filter(): string
    {
        $f = (string) $this->request->getParam('show');
        return in_array($f, ['moving', 'pickup', 'delivered', 'returning', 'never'], true) ? $f : 'all';
    }

    /**
     * Current page (0-based).
     *
     * @return int
     */
    public function page(): int
    {
        return max(0, (int) $this->request->getParam('p'));
    }

    /**
     * Rows, total and how many codes were never checked.
     *
     * @return array{rows: array, total: int, never: int}
     */
    public function overview(): array
    {
        if ($this->overview === null) {
            $this->overview = $this->sync->overview(self::PER_PAGE, $this->page() * self::PER_PAGE, $this->filter());
            foreach ($this->overview['rows'] as &$r) {
                $r['order_url'] = $this->url->getUrl('sales/order/view', ['order_id' => $r['order_id']]);
                $r['events_url'] = $this->pageUrl(['track' => (int) $r['track_id']]);
                $known = $this->statusLabels();
                $status = (string) $r['status'];
                // Status that no longer exists (e.g. left by an old module): raw code, grey.
                $r['status_label'] = $known[$status] ?? $status;
                $r['tone'] = match (true) {
                    !isset($known[$status]) => 'off',
                    $status === StatusMapper::DELIVERED, $status === 'complete' => 'ok',
                    $status === StatusMapper::RETURNING, $status === 'canceled', $status === 'holded' => 'bad',
                    default => 'wait',
                };
            }
        }
        return $this->overview;
    }

    /**
     * Labels of every order status that exists (sales_order_status), cached per request.
     *
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        if ($this->statusLabels === null) {
            $this->statusLabels = array_map('strval', $this->statusCollection->create()->toOptionHash());
        }
        return $this->statusLabels;
    }

    /**
     * Filter chips: key → label.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return [
            'all' => (string) __('All'),
            'moving' => StatusMapper::LABELS[StatusMapper::IN_TRANSIT],
            'pickup' => StatusMapper::LABELS[StatusMapper::PICKUP],
            'delivered' => StatusMapper::LABELS[StatusMapper::DELIVERED],
            'returning' => StatusMapper::LABELS[StatusMapper::RETURNING],
            'never' => (string) __('Not checked yet'),
        ];
    }

    /**
     * Timeline of the code asked with ?track=, newest first; null when none.
     *
     * @return array|null
     */
    public function selected(): ?array
    {
        $trackId = (int) $this->request->getParam('track');
        if (!$trackId) {
            return null;
        }
        foreach ($this->overview()['rows'] as $r) {
            if ((int) $r['track_id'] === $trackId) {
                foreach ($this->sync->forOrder((int) $r['order_id']) as $t) {
                    if ((int) $t['track_id'] === $trackId) {
                        return $r + ['list' => $t['list']];
                    }
                }
                return $r + ['list' => []];
            }
        }
        return null;
    }

    /**
     * Bulk import waiting for confirmation (from the admin session), or null.
     *
     * @return array|null
     */
    public function importPreview(): ?array
    {
        $saved = $this->session->getData(Import::SESSION_KEY);
        return is_array($saved) ? $saved : null;
    }

    /**
     * Counts per action of the preview.
     *
     * @param array $preview
     * @return array<string, int>
     */
    public function previewCounts(array $preview): array
    {
        $out = array_fill_keys([Importer::SHIP, Importer::ADD, Importer::EXISTS, Importer::NOT_FOUND, Importer::CANNOT], 0);
        foreach ($preview as $r) {
            $out[$r['action']]++;
        }
        return $out;
    }

    /**
     * Human text and tone of an import action.
     *
     * @param string $action
     * @return array{0: string, 1: string}
     */
    public function actionLabel(string $action): array
    {
        return match ($action) {
            Importer::SHIP => [(string) __('Ship the order with this code'), 'ok'],
            Importer::ADD => [(string) __('Add the code to the shipment'), 'ok'],
            Importer::EXISTS => [(string) __('Already has this code'), 'off'],
            Importer::NOT_FOUND => [(string) __('Order not found'), 'bad'],
            default => [(string) __('Order cannot be shipped'), 'bad'],
        };
    }

    /**
     * Whether the background check, the automatic status and the e-mails are on.
     *
     * @return array{sync: bool, status: bool, notify: bool}
     */
    public function automation(): array
    {
        return [
            'sync' => $this->settings->trackingSync(),
            'status' => $this->settings->autoStatus(),
            'notify' => $this->settings->notifyCustomer(),
        ];
    }

    /**
     * URL of this page keeping the filter and the page.
     *
     * @param array $params
     * @return string
     */
    public function pageUrl(array $params = []): string
    {
        $params += ['show' => $this->filter() === 'all' ? null : $this->filter(), 'p' => $this->page() ?: null];
        return $this->url->getUrl('frenetshipping/tracking/index', array_filter($params, static fn ($v) => $v !== null));
    }

    /**
     * Admin URL.
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function url(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }
}
