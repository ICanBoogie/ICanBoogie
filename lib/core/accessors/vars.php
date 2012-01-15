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
 * Accessor for the variables stored as files in the "/repository/var" directory.
 */
class Vars implements \ArrayAccess
{
	const MAGIC = "VAR\0SLZ\0";
	const MAGIC_LENGTH = 8;

	/**
	 * Absolute path to the vars directory.
	 *
	 * @var string
	 */
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
		$filename = $this->path . $name;

		return file_exists($filename);
	}

	/**
	 * Deletes a var.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($name)
	{
		$filename = $this->path . $name;

		if (!file_exists($filename))
		{
			return;
		}

		unlink($filename);
	}

	/**
	 * Returns the value of the var using the {@link retrieve()} method.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($name)
	{
		return $this->retrieve($name);
	}

	/**
	 * Cache a variable in the repository.
	 *
	 * If the value is an array or a string it is serialized and prepended with a magic
	 * indentifier. This magic identifier is used to recognized previously serialized values when
	 * they are read back.
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
		$filename = $this->path . $key;
		$ttl_mark = $filename . '.ttl';

		if ($ttl)
		{
			$future = time() + $ttl;

			touch($ttl_mark, $future, $future);
		}
		else if (file_exists($ttl_mark))
		{
			unlink($ttl_mark);
		}

		$dir = dirname($filename);

		if (!file_exists($dir))
		{
			mkdir($dir, 0755, true);
		}

		$tmp_filename = 'var-' . uniqid(mt_rand(), true);

		#
		# If the value is an array or a string it is serialized and prepended with a magic
		# identifier.
		#

		if (is_array($value) || is_object($value))
		{
			$value = self::MAGIC . serialize($value);
		}

		#
		# We lock the file create/update, but we write the data in a temporary file, which is then
		# renamed once the data is written.
		#

		$fh = fopen($filename, 'a+');

		if (!$fh)
		{
			throw new Exception('Unable to open %filename', array('filename' => $filename));
		}

		if (flock($fh, LOCK_EX))
		{
			file_put_contents($tmp_filename, $value);

			if (!unlink($filename))
			{
				throw new Exception('Unable to unlink %filename', array('filename' => $filename));
			}

			rename($tmp_filename, $filename);

			flock($fh, LOCK_UN);
		}
		else
		{
			throw new WdException('Unable to get to exclusive lock on %filename', array('filename' => $filename));
		}

		fclose($fh);
	}

	/**
	 * Returns the value of variable.
	 *
	 * If the value is marked with the magic identifier it is unserialized.
	 *
	 * @param string $name
	 * @param mixed $default The value returned if the variable does not exists. Defaults to null.
	 *
	 * @return mixed
	 */
	public function retrieve($name, $default=null)
	{
		$filename = $this->path . $name;
		$ttl_mark = $filename . '.ttl';

		if (file_exists($ttl_mark) && fileatime($ttl_mark) < time() || !file_exists($filename))
		{
			return $default;
		}

		$value = file_get_contents($filename);

		if (substr($value, 0, self::MAGIC_LENGTH) == self::MAGIC)
		{
			$value = unserialize(substr($value, self::MAGIC_LENGTH));
		}

		return $value;
	}
}