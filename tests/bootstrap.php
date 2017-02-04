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

chdir(__DIR__);

define('ICanBoogie\AUTOCONFIG_PATHNAME', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'icanboogie' . DIRECTORY_SEPARATOR . 'autoconfig.php');

require __DIR__ . '/../vendor/autoload.php';

boot();
