<?php

namespace ICanBoogie\Console;

use ICanBoogie\Application;
use ICanBoogie\Config\Builder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: "configs:list", description: "List configurations", aliases: [ "configs" ])]
final class ListConfigsCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly string $style,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];

        /** @var class-string<Builder<object>> $builder */

        foreach ($this->app->autoconfig->config_builders as $class => $builder) {
            $rows[] = [
                $builder::get_fragment_filename(),
                $class,
                $builder,
            ];
        }

        $table = new Table($output);
        $table->setHeaders([ 'Fragment', 'Config', 'Builder' ]);
        $table->setRows($rows);
        $table->setStyle($this->style);
        $table->render();

        return Command::SUCCESS;
    }
}
