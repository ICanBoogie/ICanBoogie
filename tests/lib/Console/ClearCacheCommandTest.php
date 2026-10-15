<?php

namespace Test\ICanBoogie\Console;

use ICanBoogie\Console\ClearCacheCommand;
use ICanBoogie\Console\Test\CommandTestCase;

final class ClearCacheCommandTest extends CommandTestCase
{
    public static function provideExecute(): array
    {
        return [

            [
                'cache:clear',
                ClearCacheCommand::class,
                [],
                [],
                "/Cleared: app.storage_for_configs/"
            ],

        ];
    }
}
