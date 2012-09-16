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
	 * If true loaded module are run when loaded for the first time.
	 *
	 * @var boolean
	 */
	public $autorun = false;

	/**
	 * The descriptors for the modules.
	 *
	 * @var array
	 */
	public $descriptors = array();

	/**
	 * The paths where modules can be found.
	 *
	 * @var array
	 */
	protected $paths = array();

	/**
	 * A cache for the module indexes.
	 *
	 * @var Vars
	 */
	protected $cache;

	/**
	 * Loaded modules.
	 *
	 * @var array
	 */
	protected $modules = array();

	/**
	 * The index for the available modules is created with the accessor object.
	 *
	 * @param array $paths The paths to look for modules.
	 * @param Vars $cache The cache to use for the module indexes.
	 */
	public function __construct($paths, Vars $cache=null)
	{
		$this->paths = $paths;
		$this->cache = $cache;
	}

	/**
	 * Used to enable or disable a module using the specified offset as the module's id.
	 *
	 * The module is enabled or disabled by modifying the value of the {@link Module::T_DISABLED}
	 * key of the module's descriptor.
	 *
	 * @param mixed $id Identifier of the module.
	 * @param mixed $enable Status of the module: `true` for enabled, `false` for disabled.
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
	 * A module is considered available when its descriptor is defined, and the
	 * {@link Module::T_DISABLED} key of its descriptor is empty.
	 *
	 * Note: `empty()` will call {@link offsetGet()} to check if the value is not empty. So, unless
	 * you want to use the module you check, better check using `!isset()`, otherwise the module
	 * you check is loaded too.
	 *
	 * @param string $id Identifier of the module.
	 *
	 * @return boolean Whether or not the module is available.
	 */
	public function offsetExists($id)
	{
		$descriptors = $this->descriptors;

		return (isset($descriptors[$id]) && empty($descriptors[$id][Module::T_DISABLED]));
	}

	/**
	 * Disables a module by setting the {@link Module::T_DISABLED} key of its descriptor to `true`.
	 *
	 * The method also dismisses the {@link enabled_modules_descriptors} and
	 * {@link disabled_modules_descriptors} properties.
	 *
	 * @param string $id Identifier of the module.
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
	 * Returns a module object.
	 *
	 * If the {@link autorun} property is `true`, the {@link Module::run()} method of the module
	 * is invoked upon its first loading.
	 *
	 * @param string $id The identifier of the module.
	 *
	 * @return Module
	 *
	 * @throws Exception when the module doesn't exists or the class that should be used to create its instance is
	 * not defined.
	 * @throws Exception\DisabledModule when the module is disabled.
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
			throw new Exception\DisabledModule('The module %id is disabled.', array('%id' => $id), 404);
		}

		$class = $descriptor[Module::T_CLASS];

		if (!class_exists($class, true))
		{
			throw new Exception('Missing class %class to instantiate module %id.', array('%class' => $class, '%id' => $id));
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
	 * configs path, an array of autoload classes, an array of classes aliases and finally an array
	 * of configs constructors.
	 *
	 * @return array
	 */
	protected function get_index()
	{
		if ($this->cache)
		{
			$key = 'cached_modules_' . md5(implode('#', $this->paths));
			$index = $this->cache[$key];

			if (!$index)
			{
				$index = $this->index_construct();

				$this->cache[$key] = $index;
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
		$descriptors = $this->paths ? $this->index_descriptors($this->paths) : array();

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
	 * The descriptors are ordered according to their inheritance and weight.
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
				throw new Exception('Unable to open directory %root', array('root' => $root));
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
							'path' => strip_root($descriptor_path)
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
					Module::T_VERSION => '0.0',
					Module::T_WEIGHT => 0,

					'__autoload' => array()
				);

				$descriptors[$id] = $descriptor;
			}
		}

		if (!$descriptors)
		{
			return array();
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
	 * - Autoload references are also created for each model and their active record depending on
	 * the {@link T_MODELS} tag and the exsitance of the corresponding files.
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

		# operation classes

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

		$try = $path . 'lib' . DIRECTORY_SEPARATOR . 'operations' . DIRECTORY_SEPARATOR;

		if (is_dir($try))
		{
			$dir = new \DirectoryIterator($try);
			$filter = new \RegexIterator($dir, '#\.php$#');

			foreach ($filter as $file)
			{
				$name = $file->getBasename('.php');
				$classname = Operation::format_class_name($namespace, $name);

				$autoload[$classname] = $try . $file;
			}
		}

		# controller classes

		$pathname = $path . 'lib' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR;

		if (is_dir($pathname))
		{
			$dir = new \DirectoryIterator($pathname);
			$filter = new \RegexIterator($dir, '#\.php$#');

			foreach ($filter as $file)
			{
				$name = $file->getBasename('.php');
				$classname = Controller::format_class_name($namespace, $name);

				$autoload[$classname] = $pathname . $file;
			}
		}

		# models and active records

		foreach ($descriptor[Module::T_MODELS] as $model_id => &$definition)
		{
			if (!is_array($definition))
			{
				throw new Exception('Model definition is not an array, given: %value.', array('value' => $definition));
			}

			$file_base = $path . $model_id;

			# try model

			$pathname = $file_base . '.model.php';

			if (file_exists($pathname))
			{
				if (empty($definition[Model::T_CLASS]))
				{
					$class = Model::resolve_class_name($namespace, $model_id);
					$definition[Model::T_CLASS] = $class;
				}

				if (empty($definition[Model::T_NAME]))
				{
					$definition[Model::T_NAME] = Model::format_name($id, $model_id);
				}

				$autoload[$definition[Model::T_CLASS]] = $pathname;
			}

			# try activerecord

			$pathname = $file_base . '.ar.php';

			if (file_exists($pathname))
			{
				if (empty($definition[Model::T_ACTIVERECORD_CLASS]))
				{
					$class = ActiveRecord::resolve_class_name($id, $model_id);
					$definition[Model::T_ACTIVERECORD_CLASS] = $class;
				}

				$autoload[$definition[Model::T_ACTIVERECORD_CLASS]] = $pathname;
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
	public function get_disabled_modules_descriptors()
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
	public function get_enabled_modules_descriptors()
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
	 * Orders the module ids provided according to module inheritance and weight.
	 *
	 * @param array $ids The module ids to order.
	 * @param array $descriptors Module descriptors.
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
	 * Runs the modules having a truthy {@link Module::T_STARTUP} value.
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

	/**
	 * Returns the usage of a module by other modules.
	 *
	 * @param string $module_id The identifier of the module.
	 * @param bool $all The usage is only computed for enabled module, this parameter can be used
	 * to compute the usage with disabled modules also.
	 *
	 * @return int
	 */
	public function usage($module_id, $all=false)
	{
		$n = 0;

		foreach ($this->descriptors as $m_id => $descriptor)
		{
			if (!$all && !isset($this[$m_id]))
			{
				continue;
			}

			if ($descriptor[Module::T_EXTENDS] == $module_id)
			{
				$n++;
			}

			if (!empty($descriptor[Module::T_REQUIRES][$module_id]))
			{
				$n++;
			}
		}

		return $n;
	}
}

namespace ICanBoogie\Exception;

/**
 * This exception is thrown when a disabled module is requested.
 */
class DisabledModule extends \ICanBoogie\Exception
{

}