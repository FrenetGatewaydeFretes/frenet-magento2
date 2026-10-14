<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\Labels\Compare;
use Frenet\Shipping\Model\Labels\Review;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * "Create shipment": step 1 picks orders, step 2 compares carriers for them before creating.
 */
class CreateShipment implements ArgumentInterface
{
    /** @var array|null */
    private ?array $review = null;

    /**
     * @param RequestInterface $request
     * @param Review $reviewModel
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Review $reviewModel
    ) {
    }

    /**
     * Selected order ids (?ids=1,2,3 or ids[]).
     *
     * @return int[]
     */
    public function ids(): array
    {
        $ids = $this->request->getParam('ids', []);
        $ids = is_array($ids) ? $ids : explode(',', (string) $ids);
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * Search typed in step 1.
     *
     * @return string
     */
    public function search(): string
    {
        return trim((string) $this->request->getParam('q'));
    }

    /**
     * Orders that can get a Frenet shipment.
     *
     * @return array
     */
    public function candidates(): array
    {
        return $this->reviewModel->candidates($this->search());
    }

    /**
     * Comparison of the selected orders (computed once).
     *
     * @return array
     */
    public function review(): array
    {
        return $this->review ??= $this->reviewModel->review($this->ids());
    }

    /**
     * Totals of the preselected services, for the first render (the page script recalculates them).
     *
     * @return array
     */
    public function initialSummary(): array
    {
        $chosen = [];
        foreach ($this->review()['rows'] as $row) {
            if ($row['ok'] && $row['selected'] !== null) {
                $chosen[] = $row['services'][$row['selected']];
            }
        }
        return Compare::summarize($chosen);
    }
}
