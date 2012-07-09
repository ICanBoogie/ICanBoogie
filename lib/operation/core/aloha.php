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
 * Displays information about the core and its modules.
 */
class AlohaOperation extends Operation
{
	protected function validate(Errors $errors)
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

		$this->response->content_type = 'text/plain';

		$rc  = 'ICanBoogie v' . VERSION . ' is running here with:';
		$rc .= PHP_EOL . PHP_EOL . implode(PHP_EOL, $enabled);
		$rc .= PHP_EOL . PHP_EOL . 'Disabled modules:';
		$rc .= PHP_EOL . PHP_EOL . implode(PHP_EOL, $disabled);

		return $rc;
	}
}