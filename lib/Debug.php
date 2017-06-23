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
 * @codeCoverageIgnore
 */
class Debug
{
	const MODE_DEV = 'dev';
	const MODE_STAGE = 'stage';
	const MODE_PRODUCTION = 'production';

	static public $mode = 'dev';

	static public function synthesize_config(array $fragments)
	{
		$config = array_merge_recursive(...array_values($fragments));
		$config = array_merge($config, $config['modes'][$config['mode']]);

		return $config;
	}

	static private $config;

	static private $config_code_sample = true;
	static private $config_line_number = true;
	static private $config_stack_trace = true;
	static private $config_exception_chain = true;
	static private $config_verbose = true;

	static public function is_dev()
	{
		return self::$mode == self::MODE_DEV;
	}

	static public function is_stage()
	{
		return self::$mode == self::MODE_STAGE;
	}

	static public function is_production()
	{
		return self::$mode == self::MODE_PRODUCTION;
	}

	/**
	 * Configures the class.
	 *
	 * @param array $config A config such as one returned by `$app->configs['debug']`.
	 */
	static public function configure(array $config)
	{
		$mode = self::$mode;
		$modes = [];

		foreach ($config as $directive => $value)
		{
			if ($directive == 'mode')
			{
				$mode = $value;

				continue;
			}
			else if ($directive == 'modes')
			{
				$modes = $value;

				continue;
			}

			$directive = 'config_' . $directive;

			self::$$directive = $value;
		}

		self::$mode = $mode;

		if (isset($modes[$mode]))
		{
			foreach ($modes[$mode] as $directive => $value)
			{
				$directive = 'config_' . $directive;

				self::$$directive = $value;
			}
		}
	}

	/*
	**

	DEBUG & TRACE

	**
	*/

	const MAX_STRING_LEN = 16;

	static private $error_names = [

		E_ERROR => 'Error',
		E_WARNING => 'Warning',
		E_PARSE => 'Parse error',
		E_NOTICE => 'Notice'

	];

	static private function get_logger()
	{
		return app()->logger;
	}

	/**
	 * The method is forwarded to the application's logger `get_messages()` method.
	 *
	 * @param $level
	 *
	 * @return \string[]
	 */
	static public function get_messages($level)
	{
		return self::get_logger()->get_messages($level);
	}

	/**
	 * The method is forwarded to the application's logger `fetch_messages()` method.
	 *
	 * @param $level
	 *
	 * @return \string[]
	 */
	static public function fetch_messages($level)
	{
		return self::get_logger()->fetch_messages($level);
	}
}
