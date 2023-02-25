<?php

namespace ICanBoogie\Console;

use ICanBoogie\Application;
use ICanBoogie\Autoconfig\Autoconfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ListConfigsCommand extends Command
{
    protected static $defaultDescription = "List configurations";

    public function __construct(
        private readonly Application $app,
        private readonly string $style,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];

        foreach ($this->app->autoconfig->config_builders as $class => $builder) {
            $rows[] = [
                $class,
                $builder,
            ];
        }

        $table = new Table($output);
        $table->setHeaders([ 'Config', 'Builder' ]);
        $table->setRows($rows);
        $table->setStyle($this->style);
        $table->render();

        return Command::SUCCESS;
    }
}
