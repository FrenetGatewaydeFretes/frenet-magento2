<?php
/**
 * Frenet Shipping Gateway — every 30 minutes, reads the Frenet events of the shipped orders.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Cron;

use Frenet\Shipping\Model\Extras\Settings;
use Frenet\Shipping\Model\TrackingSync\Sync;

class SyncTracking
{
    /**
     * @param Sync $sync
     * @param Settings $settings
     */
    public function __construct(
        private readonly Sync $sync,
        private readonly Settings $settings
    ) {
    }

    /**
     * Runs one batch when the automatic tracking is on.
     *
     * @return void
     */
    public function execute(): void
    {
        if ($this->settings->trackingSync()) {
            $this->sync->run();
        }
    }
}
