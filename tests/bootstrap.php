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

$_SERVER['DOCUMENT_ROOT'] = __DIR__;

define('ICanBoogie\AUTOCONFIG_PATHNAME', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'icanboogie' . DIRECTORY_SEPARATOR . 'auto-config.php');

require __DIR__ . '/../vendor/autoload.php';

if (!file_exists(REPOSITORY))
{
	mkdir(REPOSITORY);
}

if (!file_exists(REPOSITORY . 'vars'))
{
	mkdir(REPOSITORY . 'vars');
}