<?php
/**
 * Frenet Shipping Gateway — bin/magento frenet:tracking:sync [--order=<increment id>]
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Console\Command;

use Frenet\Shipping\Model\TrackingSync\Sync;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TrackingSyncCommand extends Command
{
    /**
     * @param Sync $sync
     * @param State $state
     * @param CollectionFactory $orders
     */
    public function __construct(
        private readonly Sync $sync,
        private readonly State $state,
        private readonly CollectionFactory $orders
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('frenet:tracking:sync')
            ->setDescription('Reads the Frenet tracking events now (all due codes, or one order).')
            ->addOption('order', null, InputOption::VALUE_REQUIRED, 'Order increment id');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
            unset($e);
        }
        $orderId = null;
        if ($input->getOption('order')) {
            $order = $this->orders->create()->addFieldToFilter('increment_id', (string) $input->getOption('order'))->getFirstItem();
            if (!$order->getId()) {
                $output->writeln('<error>Order not found.</error>');
                return Command::FAILURE;
            }
            $orderId = (int) $order->getId();
        }
        $r = $this->sync->run($orderId);
        $output->writeln(sprintf('<info>%d code(s) read, %d order status(es) changed, %d error(s).</info>', $r['synced'], $r['changed'], $r['errors']));
        return $r['errors'] && !$r['synced'] ? Command::FAILURE : Command::SUCCESS;
    }
}
