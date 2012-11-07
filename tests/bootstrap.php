<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__ . '/../vendor/icanboogie/common/bootstrap.php';
require_once __DIR__ . '/../vendor/icanboogie/prototype/bootstrap.php';
require_once __DIR__ . '/../vendor/icanboogie/activerecord/bootstrap.php';
require_once __DIR__ . '/../vendor/icanboogie/event/bootstrap.php';
require_once __DIR__ . '/../bootstrap.php';

$loader = require __DIR__ . '/../vendor/autoload.php';

$core = new ICanBoogie\Core();