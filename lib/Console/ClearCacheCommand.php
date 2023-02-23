<?php

namespace ICanBoogie\Console;

use ICanBoogie\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function ICanBoogie\emit;

/**
 * Clears caches.
 */
final class ClearCacheCommand extends Command
{
    protected static $defaultDescription = "Clear caches";

    public function __construct(
        private readonly Application $app
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $event = emit(new Application\ClearCacheEvent($this->app));

        foreach ($event->cleared as $cleared) {
            $output->writeln("<info>Cleared: $cleared</info>");
        }

        return Command::SUCCESS;
    }
}
