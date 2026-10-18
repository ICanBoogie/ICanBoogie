<?php

/*
 * Boots and runs a fresh application for the scenario given as argument, and prints a JSON document
 * with the `Server-Timing` header of the response, or the exception that escaped `run()`.
 */

namespace Test\ICanBoogie\ServerTiming;

use ICanBoogie\Application;
use ICanBoogie\Autoconfig\Autoconfig;
use Throwable;

use function assert;
use function chdir;
use function dirname;
use function is_string;
use function iterator_to_array;
use function json_encode;
use function ob_end_clean;
use function ob_start;
use function putenv;

chdir(dirname(__DIR__, 2));

require __DIR__ . '/../../../vendor/autoload.php';

$scenario = ((array) $_SERVER['argv'])[1];

assert(is_string($scenario));

putenv("SERVER_TIMING_SCENARIO=$scenario");

$autoconfig = Autoconfig::get();
$app = Application::new($autoconfig->with([
    'config_paths' => $autoconfig->config_paths + [
        __DIR__ . '/config_a' => 200,
        __DIR__ . '/config_b' => 300,
    ],
]));
$app->boot();

ob_start();

try {
    $app->run();
    $result = [ 'server_timing' => iterator_to_array(FixedResponder::$response->headers->fields())['Server-Timing'] ?? null ];
} catch (Throwable $e) {
    $result = [ 'error' => $e::class, 'message' => $e->getMessage() ];
}

ob_end_clean();

echo json_encode($result);
