<?php

namespace ICanBoogie;

use function ob_start;

chdir(__DIR__);

require __DIR__ . '/../vendor/autoload.php';

ob_start(); // Prevent PHPUnit from sending headers

boot();
