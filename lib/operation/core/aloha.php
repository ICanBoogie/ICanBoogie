<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Operation\Core;

use ICanBoogie;
use ICanBoogie\Debug;
use ICanBoogie\Module;
use ICanBoogie\Operation;

/**
 * Displays information about the core and its modules.
 */
class Aloha extends Operation
{
	protected function validate()
	{
		return true;
	}

	protected function process()
	{
		global $core;

		$enabled = array();
		$disabled = array();

		foreach ($core->modules->descriptors as $module_id => $descriptor)
		{
			if (!empty($descriptor[Module::T_DISABLED]))
			{
				$disabled[] = $module_id;

				continue;
			}

			$enabled[] = $module_id;
		}

		sort($enabled);
		sort($disabled);

		header('Content-Type: text/plain; charset=utf-8');

		$rc  = 'ICanBoogie v' . ICanBoogie\VERSION . ' is running here with:';
		$rc .= PHP_EOL . PHP_EOL . implode(PHP_EOL, $enabled);
		$rc .= PHP_EOL . PHP_EOL . 'Disabled modules:';
		$rc .= PHP_EOL . PHP_EOL . implode(PHP_EOL, $disabled);
		$rc .= PHP_EOL . PHP_EOL . strip_tags(implode(PHP_EOL, Debug::fetch_messages('debug')));

		return $rc;
	}
}