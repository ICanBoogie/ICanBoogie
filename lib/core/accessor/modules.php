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

use ICanBoogie;
use ICanBoogie\ActiveRecord;
use ICanBoogie\Exception;
use ICanBoogie\FileCache;
use ICanBoogie\Module;

/**
 * Accessor class for the modules of the framework.
 *
 * @property-read array $disabled_modules_descriptors The descriptors of the disabled modules.
 * @property-read array $enabled_modules_descriptors The descriptors of the enabled modules.
 * @property-read array $index Index for the modules.
 */
class Modules extends ICanBoogie\Object implements \ArrayAccess, \IteratorAggregate
{
	/**
	 * @var boolean If true loaded module are run when loaded for the first time.
	 */
	public $autorun = false;

	/**
	 * @var array The descriptors for the modules.
	 */
	public $descriptors = array();

	/**
	 * @var array The paths where modules can be found.
	 */
	protected $paths = array();

	/**
	 * @var boolean If true a cache is used to handle the index.
	 */
	protected $use_cache = false;

	/**
	 * @var string Path to the cache repository, relative to the ICanBoogie\DOCUMENT_ROOT
	 * path.
	 */
	protected $cache_repository;

	/**
	 * @var array Loaded modules.
	 */
	private $modules = array();

	/**
	 * The index for the available modules is created with the accessor object.
	 *
	 * @param array $paths The paths to look for modules.
	 * @param bool $use_cache Should we use a cache for the module index ?
	 * @param string $cache_repository The path to the cache repository.
	 */
	public function __construct($paths, $use_cache=false, $cache_repository='/repository/cache/core')
	{
		$this->use_cache = $use_cache;
		$this->cache_repository = $cache_repository;
		$this->paths = $paths;
	}

	/**
	 * Used to enable or disable a module using the specified offset as the module's id.
	 *
	 * The module is enabled or disabled by modifying the value of the T_DISABLED key of the
	 * module's descriptor. Set the offset to true to enable the module, set it to false to disable
	 * it.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($id, $enable)
	{
		if (empty($this->descriptors[$id]))
		{
			return;
		}

		$this->descriptors[$id][Module::T_DISABLED] = empty($enable);

		unset($this->enabled_modules_descriptors);
		unset($this->disabled_modules_descriptors);
	}

	/**
	 * Checks the availability of a module.
	 *
	 * A module is considered available when its descriptor is defined, and the T_DISABLED tag of
	 * its descriptor is empty.
	 *
	 * Note: empty() will call {@link offsetGet()} to check if the value is not empty. So, unless
	 * you want to use the module you check, better check using !isset(), otherwise the module
	 * you check is loaded too.
	 *
	 * @param string $id The module's id.
	 *
	 * @return boolean Whether or not the module is available.
	 */
	public function offsetExists($id)
	{
		$descriptors = $this->descriptors;

		return (isset($descriptors[$id]) && empty($descriptors[$id][Module::T_DISABLED]));
	}

	/**
	 * Disables a module by setting the T_DISABLED key of its descriptor to TRUE.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($id)
	{
		if (empty($this->descriptors[$id]))
		{
			return;
		}

		$this->descriptors[$id][Module::T_DISABLED] = true;

		unset($this->enabled_modules_descriptors);
		unset($this->disabled_modules_descriptors);
	}

	/**
	 * Gets a module object.
	 *
	 * If the `autorun` property is TRUE, the `run()` method of the module is invoked upon its
	 * first loading.
	 *
	 * @param string $id The id of the module to get.
	 *
	 * @throws Exception If the module is not available (because it's not defined or disabled) or
	 * the class used to instanciate the module is missing.
	 *
	 * @return Module The module object.
	 */
	public function offsetGet($id)
	{
		if (isset($this->modules[$id]))
		{
			return $this->modules[$id];
		}

		$descriptors = $this->descriptors;

		if (empty($descriptors[$id]))
		{
			throw new Exception
			(
				'The module %id does not exists ! (available modules are: :list)', array
				(
					'%id' => $id,
					':list' => implode(', ', array_keys($descriptors))
				),

				404
			);
		}

		$descriptor = $descriptors[$id];

		if (!empty($descriptor[Module::T_DISABLED]))
		{
			throw new Exception('The module %id is disabled.', array('%id' => $id), 404);
		}

		$class = $descriptor['class'];

		if (!class_exists($class, true))
		{
			throw new Exception('Missing class %class to instanciate module %id.', array('%class' => $class, '%id' => $id));
		}

		$this->modules[$id] = $module = new $class($descriptors[$id]);

		if ($this->autorun)
		{
			$module->run();
		}

		return $module;
	}

	/**
	 * @see IteratorAggregate::getIterator()
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->modules);
	}

	/**
	 * Indexes the modules found in the paths specified during construct.
	 *
	 * The index is made of an array of descriptors, an array of catalogs paths, an array of
	 * configs path, an array of autoload classes, an array of classes aliases and finaly an array
	 * of configs constructors.
	 *
	 * @return array
	 */
	protected function __get_index()
	{
		if ($this->use_cache)
		{
			$cache = new FileCache
			(
				array
				(
					FileCache::T_REPOSITORY => $this->cache_repository,
					FileCache::T_SERIALIZE => true,
					FileCache::T_COMPRESS => true
				)
			);

			$index = $cache->load('modules_' . md5(implode($this->paths)), array($this, 'index_construct'));
		}
		else
		{
			$index = $this->index_construct();
		}

		$this->descriptors = $index['descriptors'];

		return $index;
	}

	/**
	 * Construct the index for the modules.
	 *
	 * @return array
	 */
	public function index_construct()
	{
		$index = array
		(
			'descriptors' => array(),
			'catalogs' => array(),
			'configs' => array(),

			'autoload' => array(),
			'classes aliases' => array(),
			'config constructors' => array()
		);

		foreach ($this->paths as $root)
		{
			try
			{
				$dir = new \DirectoryIterator($root);
			}
			catch (\Exception $e)
			{
				throw new Exception
				(
					'Unable to open directory %root', array
					(
						'%root' => $root
					)
				);
			}

			foreach ($dir as $file)
			{
				if ($file->isDot() || !$file->isDir())
				{
					continue;
				}

				$module_id = $file->getFilename();

				$module_path = $root . DIRECTORY_SEPARATOR . $module_id;
				$read = $this->index_module($module_id, $module_path . DIRECTORY_SEPARATOR);

				if ($read)
				{
					$index['descriptors'][$module_id] = $read['descriptor'];

					if (is_dir($module_path . '/locale'))
					{
						$index['catalogs'][] = $module_path;
					}

					if (is_dir($module_path . '/config'))
					{
						$index['configs'][] = $module_path;

						$core_config_path = $module_path . '/config/core.php';

						if (is_file($core_config_path))
						{
							$core_config = wd_isolated_require($core_config_path, array('path' => $module_path . '/', 'root' => $module_path . '/'));

							if (isset($core_config['autoload']))
							{
								$index['autoload'] += $core_config['autoload'];
							}

							if (isset($core_config['classes aliases']))
							{
								$index['classes aliases'] += $core_config['classes aliases'];
							}

							if (isset($core_config['config constructors']))
							{
								$index['config constructors'] += $core_config['config constructors'];
							}
						}
					}

					if ($read['autoload'])
					{
						$index['autoload'] = $read['autoload'] + $index['autoload'];
					}
				}
			}
		}

		return $index;
	}

	/**
	 * Indexes a specified module by reading its descriptor and creating an array of autoload
	 * references based on the available files.
	 *
	 * The module's descriptor is altered by adding the module's path (T_PATH) and the module's
	 * identifier (T_ID).
	 *
	 * Autoload references are generated depending on the files available and the module's
	 * descriptor:
	 *
	 * If a 'hooks.php' file exists, the "ICanBoogie\Hooks\<normalized_module_id>" reference is
	 * added to the autoload array.
	 *
	 * Autoload references are also created for each model and their activerecord depending on
	 * the T_MODELS tag and the exsitance of the corresponding files.
	 *
	 * @param string $id The module's identifier
	 * @param string $path The module's directory
	 *
	 * @return array
	 */
	protected function index_module($id, $path)
	{
		$descriptor_path = $path . 'descriptor.php';
		$descriptor = require $descriptor_path;

		if (!is_array($descriptor))
		{
			throw new Exception
			(
				'%var should be an array: %type given instead in %path', array
				(
					'%var' => 'descriptor',
					'%type' => gettype($descriptor),
					'%path' => wd_strip_root($descriptor_path)
				)
			);
		}

		$flat_id = strtr($id, '.', '_');
		$normalized_namespace_part = ICanBoogie\normalize_namespace_part($id);

		$descriptor = array
		(
			Module::T_PATH => $path,
			Module::T_ID => $id,

			'class' => 'ICanBoogie\Module\\' .$normalized_namespace_part
		)

		+ $descriptor;

		$autoload = array();

		if (file_exists($path . 'module.php'))
		{
			$autoload['ICanBoogie\Module\\' . $normalized_namespace_part] = $path . 'module.php';
		}

		if (file_exists($path . 'hooks.php'))
		{
			$autoload['ICanBoogie\Hooks\\' . $normalized_namespace_part] = $path . 'hooks.php';
		}

		$operations_dir = $path . 'operations' . DIRECTORY_SEPARATOR;

		if (is_dir($operations_dir))
		{
			$dir = new \DirectoryIterator($operations_dir);
			$filter = new \RegexIterator($dir, '#\.php$#');

			foreach ($filter as $file)
			{
				$base = $file->getBasename('.php');
				$name = 'ICanBoogie\Operation\\' . $normalized_namespace_part . '\\' . ICanBoogie\normalize_namespace_part($base);

				$autoload[$name] = $operations_dir . $file;
			}
		}

		if (isset($descriptor[Module::T_MODELS]))
		{
			$model_base = 'ICanBoogie\ActiveRecord\Model\\'  . $normalized_namespace_part;

			foreach ($descriptor[Module::T_MODELS] as $model_id => $dummy)
			{
				$class_base = $flat_id . ($model_id == 'primary' ? '' : '_' . $model_id);
				$file_base = $path . $model_id;

				if (file_exists($file_base . '.model.php'))
				{
					$autoload[$model_base . ($model_id == 'primary' ? '' : '\\' . ICanBoogie\normalize_namespace_part($model_id))] = $file_base . '.model.php';
				}

				$file = $file_base . '.ar.php';

				if (file_exists($file))
				{
					$class = ActiveRecord::resolve_class_name($id, $model_id);
					$autoload[$class] = $file;
				}
			}
		}

		return array
		(
			'descriptor' => $descriptor,
			'autoload' => $autoload
		);
	}

	/**
	 * Returns the descriptors of the disabled modules.
	 *
	 * This method is the getter for the {@link $disabled_modules_descriptors} magic property.
	 *
	 * @return array
	 */
	public function __get_disabled_modules_descriptors()
	{
		$this->sort_modules_descriptors();

		return $this->disabled_modules_descriptors;
	}

	/**
	 * Returns the descriptors of the enabled modules.
	 *
	 * This method is the getter for the {@link $enabled_modules_descriptors} magic property.
	 *
	 * @return array
	 */
	public function __get_enabled_modules_descriptors()
	{
		$this->sort_modules_descriptors();

		return $this->enabled_modules_descriptors;
	}

	/**
	 * Traverses the descriptors and create two array of descriptors: one for the disabled modules
	 * and the other for enabled modules. The {@link $disabled_modules_descriptors} magic property
	 * receives the descriptors of the disabled modules, while the {@link $enabled_modules_descriptors}
	 * magic property receives the descriptors of the enabled modules.
	 */
	private function sort_modules_descriptors()
	{
		$enabled = array();
		$disabled = array();

		foreach ($this->descriptors as $id => &$descriptor)
		{
			if (isset($this[$id]))
			{
				$enabled[$id] = $descriptor;
			}
			else
			{
				$disabled[$id] = $descriptor;
			}
		}

		$this->enabled_modules_descriptors = $enabled;
		$this->disabled_modules_descriptors = $disabled;
	}

	/**
	 * Run the modules having a non null T_STARTUP value.
	 *
	 * The modules to run are sorted using the value of the T_STARTUP tag.
	 *
	 * The T_STARTUP tag defines the priority of the module in the run sequence.
	 * The higher the value, the earlier the module is ran.
	 */
	public function run()
	{
		$list = array();

		foreach ($this->descriptors as $id => $descriptor)
		{
			if (!isset($descriptor[Module::T_STARTUP]) || !isset($this[$id]))
			{
				continue;
			}

			$list[$id] = $descriptor[Module::T_STARTUP];
		}

		arsort($list);

		foreach ($list as $id => $priority)
		{
			$this[$id];
		}
	}
}