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
 * This class provides an accessor to synthesized low level configurations.
 */
class Configs implements \ArrayAccess
{
	protected $paths = [];
	protected $constructors = [];
	protected $configs = [];

	public $cache_repository;

	public function __construct($paths, $constructors)
	{
		$this->paths = array_combine($paths, array_fill(0, count($paths), 0));
		$this->constructors = $constructors;
	}

	public function offsetSet($offset, $value)
	{
		throw new OffsetNotWritable([ $offset, $this ]);
	}

	/**
	 * Checks if a config has been synthesized.
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($offset)
	{
		isset($this->configs[$offsets]);
	}

	/**
	 * @throws OffsetNotWritable in attempt to unset an offset.
	 */
	public function offsetUnset($offset)
	{
		throw new OffsetNotWritable([ $offset, $this ]);
	}

	/**
	 * Returns the specified synthesized configuration.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($id)
	{
		if (isset($this->configs[$id]))
		{
			return $this->configs[$id];
		}

		if (empty($this->constructors[$id]))
		{
			throw new Exception('There is no constructor defined to build the %id config.', [ '%id' => $id ]);
		}

		list($constructor, $from) = $this->constructors[$id] + [ 1 => $id ];

		return $this->synthesize($id, $constructor, $from);
	}

	/**
	 * Revokes the synthsized configs.
	 *
	 * The method is usually called after the config paths have been modified.
	 */
	protected function revoke_configs()
	{
		$this->configs = [];
	}

	/**
	 * Adds a path or several paths to the config.
	 *
	 * Paths are sorted according to their weight. The order in which they were defined is
	 * preserved for paths with the same weight.
	 *
	 * @param string|array $path
	 * @param int $weight Weight of the path. The argument is discarted if `$path` is an array.
	 *
	 * @throws \InvalidArgumentException if the path is empty.
	 */
	public function add($path, $weight=0)
	{
		if (!$path)
		{
			throw new \InvalidArgumentException('$path is empty.');
		}

		$this->revoke_configs();

		if (is_array($path))
		{
			$combined = array_combine($path, array_fill(0, count($path), $weight));

			foreach ($combined as $path => $weight)
			{
				if (!file_exists($path))
				{
					trigger_error(format('Config path %path does not exists', [ 'path' => $path ]));
				}
			}

			$this->paths += $combined;
		}
		else
		{
			$this->paths[$path] = $weight;
		}

		stable_sort($this->paths);
	}

	static private $require_cache = [];

	static private function isolated_require($__file__, $path)
	{
		if (isset(self::$require_cache[$__file__]))
		{
			return self::$require_cache[$__file__];
		}

		return self::$require_cache[$__file__] = require $__file__;
	}

	/**
	 * Returns the fragments of a configuration.
	 *
	 * @param string $name Name of the configuration.
	 *
	 * @return array Where _key_ is the pathname to the fragment file and _value_ the value
	 * returned when the file was required.
	 */
	public function get_fragments($name)
	{
		$fragments = [];
		$filename = $name . '.php';

		foreach ($this->paths as $path => $weight)
		{
			$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			$pathname = $path . $filename;

			if (!file_exists($pathname))
			{
				continue;
			}

			$fragments[$path . $filename] = self::isolated_require($pathname, $path);
		}

		return $fragments;
	}

	static private $syntheses_cache;

	/**
	 * Synthesize a configuration.
	 *
	 * @param string $name Name of the configuration to synthesize.
	 * @param string|array $constructor Callback for the synthesis.
	 * @param null|string $from[optional] If the configuration is a derivative $from is the name
	 * of the source configuration.
	 *
	 * @return mixed
	 */
	public function synthesize($name, $constructor, $from=null)
	{
		if (isset($this->configs[$name]))
		{
			return $this->configs[$name];
		}

		if (!$from)
		{
			$from = $name;
		}

		$args = [ $from, $constructor ];

		if ($this->cache_repository)
		{
			$cache = self::$syntheses_cache
			? self::$syntheses_cache
			: self::$syntheses_cache = new FileCache
			([
				FileCache::T_REPOSITORY => $this->cache_repository,
				FileCache::T_SERIALIZE => true
			]);

			$rc = $cache->load('config_' . normalize($name, '_'), [ $this, 'synthesize_constructor' ], $args);
		}
		else
		{
			$rc = $this->synthesize_constructor($args);
		}

		$this->configs[$name] = $rc;

		return $rc;
	}

	public function synthesize_constructor(array $userdata)
	{
		list($name, $constructor) = $userdata;

		$fragments = $this->get_fragments($name);

		if (!$fragments)
		{
			return;
		}

		if ($constructor == 'merge')
		{
			$rc = call_user_func_array('array_merge', $fragments);
		}
		else if ($constructor == 'recursive merge')
		{
			$rc = call_user_func_array('ICanBoogie\array_merge_recursive', $fragments);
		}
		else
		{
			$rc = call_user_func($constructor, $fragments);
		}

		return $rc;
	}
}