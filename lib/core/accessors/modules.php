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

use ICanBoogie\ActiveRecord\Model;

/**
 * Accessor class for the modules of the framework.
 *
 * @property-read array $disabled_modules_descriptors The descriptors of the disabled modules.
 * @property-read array $enabled_modules_descriptors The descriptors of the enabled modules.
 * @property-read array $index Index for the modules.
 */
class Modules extends Object implements \ArrayAccess, \IteratorAggregate
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
	 * @var Vars Used to cache for indexes.
	 */
	protected $vars;

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
	public function __construct($paths, $use_cache, Vars $vars)
	{
		$this->use_cache = $use_cache;
		$this->vars = $vars;
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

		$class = $descriptor[Module::T_CLASS];

		if (!class_exists($class, true))
		{
			throw new Exception('Missing class %class to instanciate module %id.', array('%class' => $class, '%id' => $id));
		}

		$this->modules[$id] = $module = new $class($descriptor);

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
			$key = 'modules-' . md5(implode($this->paths));

			$index = $this->vars[$key];

			if (!$index)
			{
				$index = $this->index_construct();

				$this->vars[$key] = $index;
			}
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
	 * The index contains the following values:
	 *
	 * - (array) descriptors: The descriptors of the modules, ordered by weight.
	 * - (array) catalogs: Absolute paths to locale catalog directories.
	 * - (array) configs: Absolute paths to config directories.
	 * - (array) autoload: An array of _key/value_ pairs where _key_ is the name of a class and
	 * _value_ the absolute path to the file where it is defined.
	 * - (array) classes aliases: An array of _key/value_ pairs where _key_ is the alias of a class
	 * and _value_ if the real class.
	 * - (array) config constructors: An array of _key/value_ pairs where _key_ if the name of a
	 * config and _value_ is its constructor definition.
	 *
	 * @return array
	 */
	protected function index_construct()
	{
		$descriptors = $this->index_descriptors($this->paths);

		$index = array
		(
			'descriptors' => $descriptors,
			'catalogs' => array(),
			'configs' => array(),
			'autoload' => array(),
			'classes aliases' => array(),
			'config constructors' => array()
		);

		$isolated_require = function ($__file__, $__exposed__)
		{
			extract($__exposed__);

			return require $__file__;
		};

		foreach ($descriptors as $id => $descriptor)
		{
			$index['autoload'] = $descriptor['__autoload'] + $index['autoload'];

			$path = $descriptor[Module::T_PATH];

			if (is_dir($path . '/locale'))
			{
				$index['catalogs'][] = $path;
			}

			if (is_dir($path . '/config'))
			{
				$index['configs'][] = $path;

				$core_config_path = $path . '/config/core.php';

				if (is_file($core_config_path))
				{
					$core_config = $isolated_require($core_config_path, array('path' => $path));

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
		}

		return $index;
	}

	/**
	 * Indexes descriptors.
	 *
	 * The descriptors are extended with the following default values:
	 *
	 * - (string) category: null.
	 * - (string) class: Modules\<normalized_module_part>
	 * - (string) description: null.
	 * - (bool) disabled: false if required, true otherwise.
	 * - (string) extends: null.
	 * - (string) id: The module's identifier.
	 * - (array) models: Empty array.
	 * - (string) path: The absolute path to the module directory.
	 * - (string) permission: null.
	 * - (array) permissions: Empty array.
	 * - (bool) startup: false.
	 * - (bool) required: false.
	 * - (array) requires: Empty array.
	 * - (string) weight: 0.
	 *
	 * The descriptors are ordered according to their inheritence and weight.
	 *
	 * @param array $paths
	 *
	 * @throws Exception when a directory fails to open.
	 *
	 * @return array[string]array
	 */
	protected function index_descriptors(array $paths)
	{
		$descriptors = array();

		foreach ($paths as $root)
		{
			$root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

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
						'root' => $root
					)
				);
			}

			foreach ($dir as $file)
			{
				if ($file->isDot() || !$file->isDir())
				{
					continue;
				}

				$id = $file->getFilename();
				$path = $root . $id . DIRECTORY_SEPARATOR;
				$descriptor_path = $path . 'descriptor.php';
				$descriptor = require $descriptor_path;

				if (!is_array($descriptor))
				{
					throw new Exception
					(
						'%var should be an array: %type given instead in %path', array
						(
							'var' => 'descriptor',
							'type' => gettype($descriptor),
							'path' => wd_strip_root($descriptor_path)
						)
					);
				}

				if (empty($descriptor[Module::T_TITLE]))
				{
					throw new Exception
					(
						'The %name value of the %id module descriptor is empty in %path.', array
						(
							'name' => Module::T_TITLE,
							'id' => $id,
							'path' => $descriptor_path
						)
					);
				}

				/*TODO-20120108: activate version checking
				if (empty($descriptor[Module::T_VERSION]))
				{
					throw new Exception
					(
						'The %name value of the %id module descriptor is empty in %path.', array
						(
							'name' => Module::T_VERSION,
							'id' => $id,
							'path' => $descriptor_path
						)
					);
				}
				*/

				$namespace = isset($descriptor[Module::T_NAMESPACE]) ? $descriptor[Module::T_NAMESPACE] : 'ICanBoogie\Modules\\' . normalize_namespace_part($id);

				$descriptor += array
				(
					Module::T_CATEGORY => null,
					Module::T_CLASS => $namespace . '\Module',
					Module::T_DESCRIPTION => null,
					Module::T_DISABLED => empty($descriptor[Module::T_REQUIRED]),
					Module::T_EXTENDS => null,
					Module::T_ID => $id,
					Module::T_MODELS => array(),
					Module::T_NAMESPACE => $namespace,
					Module::T_PATH => $path,
					Module::T_PERMISSION => null,
					Module::T_PERMISSIONS => array(),
					Module::T_STARTUP => false,
					Module::T_REQUIRED => false,
					Module::T_REQUIRES => array(),
					Module::T_WEIGHT => 0,

					'__autoload' => array()
				);

				$descriptors[$id] = $descriptor;
			}
		}

		#
		# Compute inheritance.
		#

		$find_parents = function($id, &$parents=array()) use (&$find_parents, &$descriptors)
		{
			$parent = $descriptors[$id][Module::T_EXTENDS];

			if ($parent)
			{
				$parents[] = $parent;

				$find_parents($parent, $parents);
			}

			return $parents;
		};

		foreach ($descriptors as $id => &$descriptor)
		{
			$descriptor['__parents'] = $find_parents($id);
		}

		#
		# Orders descriptors according to their weight.
		#

		$ordered_ids = $this->order_ids(array_keys($descriptors), $descriptors);
		$descriptors = array_merge(array_combine($ordered_ids, $ordered_ids), $descriptors);

		foreach ($descriptors as $id => &$descriptor)
		{
			foreach ($descriptor[Module::T_MODELS] as $model_id => &$model_descriptor)
			{
				if ($model_descriptor == 'inherit')
				{
					$parent_descriptor = $descriptors[$descriptor[Module::T_EXTENDS]];
					$model_descriptor = $parent_descriptor[Module::T_MODELS][$model_id];
				}
			}

			$descriptor = $this->alter_descriptor($descriptor);
		}

		return $descriptors;
	}

	/**
	 * Alters the module descriptor.
	 *
	 * Creates an array of autoload references based on the available files:
	 *
	 * - If a 'module.php' file exists, the "<module_namespace>\Module" reference is added to the
	 * autoload array.
	 *
	 * - If a 'hooks.php' file exists, the "<module_namespace>\Hooks" reference is added to the
	 * autoload array.
	 *
	 * - Autoload references are also created for each model and their activerecord depending on
	 * the T_MODELS tag and the exsitance of the corresponding files.
	 *
	 * Autoload references are added to the `__autoload` property.
	 *
	 * @param array $descriptor Descriptor of the module to index.
	 *
	 * @return array The altered descriptor.
	 */
	protected function alter_descriptor(array $descriptor)
	{
		$id = $descriptor[Module::T_ID];
		$path = $descriptor[Module::T_PATH];
		$namespace = $descriptor[Module::T_NAMESPACE];

		$autoload = array();

		if (file_exists($path . 'module.php'))
		{
			$autoload[$descriptor[Module::T_CLASS]] = $path . 'module.php';
		}

		if (file_exists($path . 'hooks.php'))
		{
			$autoload[$namespace . '\Hooks'] = $path . 'hooks.php';
		}

		$operations_dir = $path . 'operations' . DIRECTORY_SEPARATOR;

		if (is_dir($operations_dir))
		{
			$dir = new \DirectoryIterator($operations_dir);
			$filter = new \RegexIterator($dir, '#\.php$#');

			foreach ($filter as $file)
			{
				$base = $file->getBasename('.php');
				$operation_class_name = Operation::format_class_name($namespace, $base);

				$autoload[$operation_class_name] = $operations_dir . $file;
			}
		}

		foreach ($descriptor[Module::T_MODELS] as $model_id => &$definition)
		{
			if (!is_array($definition))
			{
				throw new Exception('Model definition is not an array, given: %value.', array('value' => $definition));
			}

			$file_base = $path . $model_id;

// 			if (empty($definition[Model::T_CLASS]))
			{
				$try = $file_base . '.model.php';

				if (file_exists($try))
				{
					$class = Model::resolve_class_name($namespace, $model_id);
					$autoload[$class] = $try;
					$definition[Model::T_CLASS] = $class;
					$definition[Model::T_NAME] = Model::format_name($id, $model_id);
				}
			}

// 			if (empty($definition[Model::T_ACTIVERECORD_CLASS]))
			{
				$try = $file_base . '.ar.php';

				if (file_exists($try))
				{
					$class = ActiveRecord::resolve_class_name($id, $model_id);
					$autoload[$class] = $try;
					$definition[Model::T_ACTIVERECORD_CLASS] = $class;
				}
			}
		}

		$descriptor['__autoload'] = $autoload + $descriptor['__autoload'];

		return $descriptor;
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
	 * Orders the module ids provided according to module inheritence and weight.
	 *
	 * @param array $ids The module ids to order.
	 *
	 * @return array
	 */
	public function order_ids(array $ids, array $descriptors=null)
	{
		$ordered = array();
		$extends_weight = array();

		if ($descriptors === null)
		{
			$descriptors = $this->descriptors;
		}

		$count_extends = function($super_id) use (&$count_extends, &$descriptors)
		{
			$i = 0;

			foreach ($descriptors as $id => $descriptor)
			{
				if ($descriptor[Module::T_EXTENDS] !== $super_id)
				{
					continue;
				}

				$i += 1 + $count_extends($id);
			}

			return $i;
		};

		$count_required = function($required_id) use (&$descriptors, &$extends_weight)
		{
			$i = 0;

			foreach ($descriptors as $id => $descriptor)
			{
				if (empty($descriptor[Module::T_REQUIRES][$required_id]))
				{
					continue;
				}

				$i += 1 + $extends_weight[$id];
			}

			return $i;
		};

		foreach ($ids as $id)
		{
			$extends_weight[$id] = $count_extends($id);
		}

		foreach ($ids as $id)
		{
 			$ordered[$id] = -$extends_weight[$id] -$count_required($id) + $descriptors[$id][Module::T_WEIGHT];
		}

		stable_sort($ordered);

		return array_keys($ordered);
	}

	/**
	 * Runs the modules having a truthy T_STARTUP value.
	 */
	public function run()
	{
		foreach ($this->descriptors as $id => $descriptor)
		{
			if (!$descriptor[Module::T_STARTUP] || !isset($this[$id]))
			{
				continue;
			}

			#
			# loading the module is enough to run it.
			#

			$this[$id];
		}
	}
}