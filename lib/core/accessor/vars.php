<?php

/*
* This file is part of the ICanBoogie package.
*
* (c) Olivier Laviale <olivier.laviale@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ICanBoogie\Accessor;

/**
 * Accessor for the variables stored as files in the "/repository/var" directory.
 */
class Vars implements \ArrayAccess
{
	protected $path;

	/**
	 * Constructor.
	 *
	 * @param string $path Absolute path to the vars directory.
	 */
	public function __construct($path)
	{
		$this->path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Stores the value of a var using the {@link store()} method.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($name, $value)
	{
		$this->store($name, $value);
	}

	/**
	 * Checks if the var exists.
	 *
	 * @see ArrayAccess::offsetExists()
	 *
	 * @return bool true if the var exists, false otherwise.
	 */
	public function offsetExists($name)
	{
		$filename = $this->resolve_filename($name);

		return file_exists($filename);
	}

	public function offsetUnset($name)
	{
		$filename = $this->resolve_filename($name);

		if (!file_exists($filename))
		{
			return;
		}

		unlink($filename);
	}

	public function offsetGet($name)
	{
		return $this->retrieve($name);
	}

	private function resolve_filename($name)
	{
		return $this->path . $name;
	}

	/**
	 * Cache a variable in the repository.
	 *
	 * @param string $key The key used to identify the value. Keys are unique, so storing a second
	 * value with the same key will overwrite the previous value.
	 * @param mixed $value The value to store for the key.
	 * @param int $ttl The time to live in seconds for the stored value. If no _ttl_ is supplied
	 * (or if the _tll_ is __0__), the value will persist until it is removed from the cache
	 * manualy or otherwise fails to exist in the cache.
	 */
	public function store($key, $value, $ttl=0)
	{
		$ttl_mark = $this->resolve_filename($key . '.ttl');

		if ($ttl)
		{
			$future = time() + $ttl;

			touch($ttl_mark, $future, $future);
		}
		else if (file_exists($ttl_mark))
		{
			unlink($ttl_mark);
		}

		$filename = $this->resolve_filename($key);
		$dir = dirname($filename);

		if (!file_exists($dir))
		{
			mkdir($dir, 0755, true);
		}

		file_put_contents($filename, $value);
	}

	public function retrieve($name, $default=null)
	{
		$ttl_mark = $this->resolve_filename($name . '.ttl');

		if (file_exists($ttl_mark) && fileatime($ttl_mark) < time())
		{
			return $default;
		}

		$filename = $this->resolve_filename($name);

		if (!file_exists($filename))
		{
			return $default;
		}

		return file_get_contents($filename);
	}
}