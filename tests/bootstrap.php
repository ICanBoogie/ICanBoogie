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

use function ob_start;

chdir(__DIR__);

require __DIR__ . '/../vendor/autoload.php';

ob_start(); // Prevent PHPUnit from sending headers

boot();
