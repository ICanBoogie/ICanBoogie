<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Binding\PrototypedBindings;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Session;

class PingController extends Controller
{
	use PrototypedBindings;

	static private function format_time($finish)
	{
		return number_format(($finish - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 3, '.', '') . ' ms';
	}

	/**
	 * @inheritdoc
	 */
	protected function action(Request $request)
	{
		$this->response->content_type = 'text/plain';

		if (Session::exists())
		{
			$this->app->session;
		}

		$rc = 'pong';

		if ($this->request['timer'] !== null)
		{
			$boot_time = self::format_time($_SERVER['ICANBOOGIE_READY_TIME_FLOAT']);
			$run_time = self::format_time(microtime(true));

			$rc .= ", in $run_time (ready in $boot_time)";
		}

		return $rc;
	}
}