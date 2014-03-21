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

define('ICanBoogie\AUTOCONFIG_PATHNAME', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'icanboogie' . DIRECTORY_SEPARATOR . 'auto-config.php');

define('ICanBoogie\REPOSITORY', __DIR__ . DIRECTORY_SEPARATOR . 'repository' . DIRECTORY_SEPARATOR);

if (!file_exists(REPOSITORY))
{
	mkdir(REPOSITORY);
}

if (!file_exists(REPOSITORY . 'vars'))
{
	mkdir(REPOSITORY . 'vars');
}

require __DIR__ . '/../vendor/autoload.php';