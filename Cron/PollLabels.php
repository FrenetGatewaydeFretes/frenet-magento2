<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Cron;

use Frenet\Shipping\Model\Labels\LabelsConfig;
use Frenet\Shipping\Model\Labels\ShipmentService;

/**
 * Every minute: fetches labels Frenet has not released yet and settles payments that did not answer.
 */
class PollLabels
{
    /**
     * @param ShipmentService $shipments
     * @param LabelsConfig $config
     */
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly LabelsConfig $config
    ) {
    }

    /**
     * Runs the poll when labels are configured.
     *
     * @return void
     */
    public function execute(): void
    {
        if ($this->config->isAvailable()) {
            $this->shipments->poll();
        }
    }
}
