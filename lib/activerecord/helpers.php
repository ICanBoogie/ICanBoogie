<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\ActiveRecord;

/**
 * Returns the requested model.
 *
 * @param string $id Model identifier.
 *
 * @return Model
 */
function get_model($id)
{
	return Helpers::get_model($id);
}

class Helpers
{
	static private $jumptable = array
	(
		'get_model' => array(__CLASS__, 'get_model')
	);

	/**
	 * Calls the callback of a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param array $arguments Arguments.
	 *
	 * @return mixed
	 */
	static public function __callstatic($name, array $arguments)
	{
		return call_user_func_array(self::$jumptable[$name], $arguments);
	}

	/**
	 * Patches a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param collable $callback Callback.
	 *
	 * @throws \RuntimeException is attempt to patch an undefined function.
	 */
	static public function patch($name, $callback)
	{
		if (empty(self::$jumptable[$name]))
		{
			throw new \RuntimeException("Undefined patchable: $name.");
		}

		self::$jumptable[$name] = $callback;
	}

	/*
	 * Fallbacks
	 */

	static private function get_model($id)
	{
		throw new \RuntimeException("The function " . __FUNCTION__ . "() needs to be patched.");
	}
}

/**
 * Generic Active Record exception class.
 */
class ActiveRecordException extends \Exception
{

}