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

use ICanBoogie\Operation;
use ICanBoogie\Session;

/**
 * Keeps the user's session alive.
 *
 * Only already created sessions are kept alive, new sessions will *not* be created.
 */
class Ping extends Operation
{
	protected function validate(\ICanboogie\Errors $errors)
	{
		return true;
	}

	protected function process()
	{
		global $core, $wddebug_time_reference;

		$this->response->content_type = 'text/plain';

		if (Session::exists())
		{
			$core->session;
		}

		$rc = 'pong';

		if ($this->request['timer'] !== null)
		{
			$rc .= ', in ' . number_format(microtime(true) - $wddebug_time_reference, 3, '.', '') . ' secs.';
		}

		return $rc;
	}
}