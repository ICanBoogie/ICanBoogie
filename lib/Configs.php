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
 * Provides synthesized low-level configurations.
 */
class Configs implements \ArrayAccess
{
	/**
	 * An array of key/value where _key_ is a path to a config directory and _value_ is its weight.
	 * The array is sorted according to the weight of the paths.
	 *
	 * @var array
	 */
	protected $paths = [];

	/**
	 * Short hash of the current paths.
	 *
	 * @var string
	 */
	protected $paths_hash;

	/**
	 * Callbacks to synthesize the configurations.
	 *
	 * @var array
	 */
	protected $constructors = [];

	/**
	 * Synthesized configurations.
	 *
	 * @var array
	 */
	protected $synthesized = [];

	/**
	 * A cache to store and retrieve the synthesized configurations.
	 *
	 * @var StorageInterface
	 */
	public $cache;

	/**
	 * Initialize the {@link $paths}, {@link $paths_hash}, {@link $constructors},
	 * and {@link $cache} properties.
	 *
	 * @param array $paths An array of key/value pairs where _key_ is the path to a config
	 * directory and _value_ is the weight of that path.
	 * @param array $constructors
	 * @param StorageInterface $cache
	 */
	public function __construct(array $paths, array $constructors, StorageInterface $cache=null)
	{
		$this->constructors = $constructors;
		$this->cache = $cache;

		$this->add($paths);
	}

	public function offsetSet($offset, $value)
	{
		throw new OffsetNotWritable([ $offset, $this ]);
	}

	/**
	 * Checks if a config has been synthesized.
	 */
	public function offsetExists($id)
	{
		return isset($this->synthesized[$id]);
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
	 */
	public function offsetGet($id)
	{
		if ($this->offsetExists($id))
		{
			return $this->synthesized[$id];
		}

		if (empty($this->constructors[$id]))
		{
			throw new Exception('There is no constructor defined to build the %id config.', [ '%id' => $id ]);
		}

		list($constructor, $from) = $this->constructors[$id] + [ 1 => $id ];

		return $this->synthesize($id, $constructor, $from);
	}

	/**
	 * Revokes the synthesized configs.
	 *
	 * The method is usually called after the config paths have been modified.
	 */
	protected function revoke_synthesized()
	{
		$this->synthesized = [];
	}

	/**
	 * Adds a path or several paths to the config.
	 *
	 * Paths are sorted according to their weight. The order in which they were defined is
	 * preserved for paths with the same weight.
	 *
	 * <pre>
	 * <?php
	 *
	 * $config->add('/path/to/config', 10);
	 * $config->add([
	 *
	 *     '/path1/to/config' => 10,
	 *     '/path2/to/config' => 10,
	 *     '/path2/to/config' => -10
	 *
	 * ]);
	 * </pre>
	 *
	 * @param string|array $path
	 * @param int $weight Weight of the path. The argument is discarded if `$path` is an array.
	 *
	 * @throws \InvalidArgumentException if the path is empty.
	 */
	public function add($path, $weight=0)
	{
		if (!$path)
		{
			throw new \InvalidArgumentException('$path is empty.');
		}

		$this->revoke_synthesized();
		$paths = $this->paths;

		if (is_array($path))
		{
			$paths = array_merge($paths, $path);
		}
		else
		{
			$paths[$path] = $weight;
		}

		stable_sort($paths);

		$this->paths = $paths;
		$this->paths_hash = substr(sha1(implode('|', array_keys($paths))), 0, 8);
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
		if (isset($this->synthesized[$name]))
		{
			return $this->synthesized[$name];
		}

		if (!$from)
		{
			$from = $name;
		}

		$args = [ $from, $constructor ];
		$cache = $this->cache;
		$cache_key = $this->build_cache_key($name);

		if ($cache)
		{
			$config = $cache->retrieve($cache_key);

			if ($config === null)
			{
				$config = $this->synthesize_constructor($from, $constructor);

				$cache->store($cache_key, $config);
			}
		}
		else
		{
			$config = $this->synthesize_constructor($from, $constructor);
		}

		$this->synthesized[$name] = $config;

		return $config;
	}

	private function synthesize_constructor($name, $constructor)
	{
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

	/**
	 * Build a cache key according to the current paths and the config name.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	private function build_cache_key($name)
	{
		return $this->paths_hash . '_' . $name;
	}
}
