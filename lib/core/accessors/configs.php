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
	protected $paths = array();
	protected $configs = array();

	public $cache_syntheses = false;
	public $cache_repository = '/repository/cache/core/';

	public $constructors = array
	(
		'core' => array('recursive merge', 'core')
	);

	protected $core;

	public function __construct(Core $core)
	{
		$this->core = $core;
	}

	public function offsetSet($offset, $value)
	{
		throw new Exception\OffsetNotWritable(array($offset, $this));
	}

	public function offsetExists($offset)
	{
		throw new Exception\OffsetNotReadable(array($offset, $this));
	}

	public function offsetUnset($offset)
	{
		throw new Exception\OffsetNotWritable(array($offset, $this));
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
			throw new Exception('There is no constructor defined to build the %id config.', array('%id' => $id));
		}

		list($constructor, $from) = $this->constructors[$id] + array(1 => $id);

		return $this->synthesize($id, $constructor, $from);
	}

	public function add($path, $weight=0)
	{
		$this->sorted_paths = null;

		if (is_array($path))
		{
			$combined = array_combine($path, array_fill(0, count($path), $weight));

			foreach ($combined as $path => $weight)
			{
				if (!file_exists($path))
				{
					trigger_error(format('Config path %path does not exists', array('path' => $path)));
				}
			}

			$this->paths += $combined;

			return;
		}

		$this->paths[$path] = $weight;
	}

	protected $sorted_paths;

	/**
	 * Sorts paths by weight while preserving their order.
	 *
	 * Because PHP's sorting algorithm does not respect the order in which entries are added,
	 * we need to create a temporary table to sort them.
	 *
	 * @return array Sorted paths by weight, from heavier to lighter.
	 */
	protected function get_sorted_paths() // TODO-20120813: use stable_sort()
	{
		$by_weight = array();

		foreach ($this->paths as $path => $weight)
		{
			$by_weight[$weight][] = $path;
		}

		arsort($by_weight);

		return $this->sorted_paths = call_user_func_array('array_merge', array_values($by_weight));
	}

	static private $require_cache = array();

	static private function isolated_require($__file__, $path)
	{
		if (isset(self::$require_cache[$__file__]))
		{
			return self::$require_cache[$__file__];
		}

		return self::$require_cache[$__file__] = require $__file__;
	}

	private $disabled_paths;

	private function get($name, $paths)
	{
		$disabled = $this->disabled_paths;
		$fragments = array();

		if (!$disabled && isset($this->core->modules))
		{
			foreach ($this->core->modules->disabled_modules_descriptors as $module_id => $descriptor)
			{
				$disabled[$descriptor[Module::T_PATH]] = true;
			}

			$this->disabled_paths = $disabled;
		}

		foreach ($paths as $path)
		{
			$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			if (isset($disabled[$path]))
			{
				continue;
			}

			$file = $path . 'config/' . $name . '.php';

			if (!file_exists($file))
			{
				continue;
			}

			$fragments[$path] = self::isolated_require($file, $path);
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

		$args = array($from, $constructor);

		if ($this->cache_syntheses)
		{
			$cache = self::$syntheses_cache ? self::$syntheses_cache : self::$syntheses_cache = new FileCache
			(
				array
				(
					FileCache::T_REPOSITORY => $this->cache_repository,
					FileCache::T_SERIALIZE => true
				)
			);

			$rc = $cache->load('config_' . normalize($name, '_'), array($this, 'synthesize_constructor'), $args);
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

		$fragments = $this->get($name, $this->sorted_paths ? $this->sorted_paths : $this->get_sorted_paths());

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