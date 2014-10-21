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

/**
 * Adds the `SUCCESS` level to the PSR.
 */
class LogLevel extends \Psr\Log\LogLevel
{
	const SUCCESS = 'success';
}
