<?php

/*
 * Runs the config provider in a process where no configuration was built, and prints the metrics.
 * A static property cannot be uninitialized, hence the process.
 */

namespace Test\ICanBoogie\ServerTiming;

use ICanBoogie\ConfigServerTimingProvider;
use ICanBoogie\HTTP\Headers\ServerTiming;

use function json_encode;

require __DIR__ . '/../../../vendor/autoload.php';

$timing = new ServerTiming();
new ConfigServerTimingProvider()->alter($timing);

echo json_encode((string) $timing);
