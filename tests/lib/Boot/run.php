<?php

/*
 * Boots a fresh application with the boot callables given as arguments, and prints a JSON document
 * with the log of the fixture, or the exception message.
 */

namespace Test\ICanBoogie\Boot;

use ICanBoogie\Application;
use ICanBoogie\Autoconfig\Autoconfig;
use Throwable;

use function array_slice;
use function chdir;
use function dirname;
use function is_callable;
use function is_string;
use function json_encode;

chdir(dirname(__DIR__, 2));

require __DIR__ . '/../../../vendor/autoload.php';

$callables = [];

foreach (array_slice((array) $_SERVER['argv'], 1) as $callable) {
    if (is_string($callable) && is_callable($callable)) {
        $callables[] = $callable;
    }
}

$app = Application::new(
    Autoconfig::get()->with([ 'boot_callables' => $callables ])
);

$app->events->attach(BootCallables::on_boot(...));

try {
    $app->boot();
    $result = [ 'log' => BootCallables::$log ];
} catch (Throwable $e) {
    $result = [ 'error' => $e::class, 'message' => $e->getMessage(), 'log' => BootCallables::$log ];
}

echo json_encode($result);
