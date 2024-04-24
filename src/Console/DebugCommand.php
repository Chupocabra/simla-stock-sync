<?php

namespace App\Console;

use App\Service\OrderService;
use App\Service\StockService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DebugCommand extends Command
{
    protected static $defaultName = 'app:debug';
    /**
     * @var StockService
     */
    private $stockService;
    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(
        StockService $stockService, OrderService $orderService
    )
    {
        parent::__construct();
        
        $this->stockService = $stockService;
        $this->orderService = $orderService;
    }

    protected function configure()
    {
        parent::configure();

        $this->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Order id');
        $this->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orderId  = $input->getOption('order');
        $siteCode = $input->getOption('site');

        if (!$orderId || !$siteCode) {
            throw new RuntimeException('Options --order and --site are required');
        }

        // like ArgumentResolver\OrderResolver
        $order = $this->orderService->getOrder($orderId, $siteCode);

        // like Controller\StockController
        $this->stockService->updateStock($order);

        return Command::SUCCESS;
    }
}
