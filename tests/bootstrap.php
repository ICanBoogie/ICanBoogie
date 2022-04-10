<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use function dirname;
use function ob_start;

use const DIRECTORY_SEPARATOR;

chdir(__DIR__);

/*
 * Careful! PHPUnit cannot be required as a dependency for it will trigger autoload before running
 * this file, which includes running the bootstrap file.
 */
define('ICanBoogie\AUTOCONFIG_PATHNAME', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'icanboogie' . DIRECTORY_SEPARATOR . 'autoconfig.php');

require __DIR__ . '/../vendor/autoload.php';

ob_start(); // Prevent PHPUnit from sending headers

boot();
