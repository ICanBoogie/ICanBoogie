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

use Brickrouge\format;

use ICanBoogie\ActiveRecord\Model;

/**
 * Modules manager.
 *
 * @property-read array $config_paths Paths of the enabled modules having a `config` directory.
 * @property-read array $locale_paths Paths of the enabled modules having a `locale` directory.
 * @property-read array $disabled_modules_descriptors Descriptors of the disabled modules.
 * @property-read array $enabled_modules_descriptors Descriptors of the enabled modules.
 * @property-read array $index Index for the modules.
 */
class Modules extends Object implements \ArrayAccess, \IteratorAggregate
{
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
	 * Revokes constructions.
	 *
	 * The following properties are revoked:
	 *
	 * - {@link $enabled_modules_descriptors}
	 * - {@link $disabled_modules_descriptors}
	 * - {@link $catalog_paths}
	 * - {@link $config_paths}
	 *
	 * The method is usually invoked when modules state changes, in order to reflect these
	 * changes.
	 */
	protected function revoke_constructions()
	{
		unset($this->enabled_modules_descriptors);
		unset($this->disabled_modules_descriptors);
		unset($this->catalog_paths);
		unset($this->config_paths);
	}

	/**
	 * Enables a module.
	 *
	 * @param string $id Identifier of the module.
	 */
	public function enable($id)
	{
		$this->index;

		if (empty($this->descriptors[$id]))
		{
			return;
		}

		$this->descriptors[$id][Module::T_DISABLED] = false;
		$this->revoke_constructions();
	}

	/**
	 * Disables a module.
	 *
	 * @param string $id Identifier of the module.
	 */
	public function disable($id)
	{
		$this->index;

		if (empty($this->descriptors[$id]))
		{
			return;
		}

		$this->descriptors[$id][Module::T_DISABLED] = true;
		$this->revoke_constructions();
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
		$this->revoke_constructions();
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
		$this->revoke_constructions();
	}

	/**
	 * Returns a module object.
	 *
	 * If the {@link autorun} property is `true`, the {@link Module::run()} method of the module
	 * is invoked upon its first loading.
	 *
	 * @param string $id The identifier of the module.
	 *
	 * @throws ModuleNotDefined when the requested module is not defined.
	 *
	 * @throws ModuleIsDisabled when the module is disabled.
	 *
	 * @throws Exception when the class that should be used to create its instance is not defined.
	 *
	 * @return Module
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
			throw new ModuleNotDefined($id);
		}

		$descriptor = $descriptors[$id];

		if (!empty($descriptor[Module::T_DISABLED]))
		{
			throw new ModuleIsDisabled($id);
		}

		$class = $descriptor[Module::T_CLASS];

		if (!class_exists($class, true))
		{
			throw new Exception('Missing class %class to instantiate module %id.', array('%class' => $class, '%id' => $id));
		}

		return $this->modules[$id] = new $class($descriptor);
	}

	/**
	 * Returns an iterator for the modules.
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
	 * configs path, and finally an array of configs constructors.
	 *
	 * The method also creates a `DIR` constant for each module. The constant is defined in the
	 * namespace of the module e.g. `Icybee\Modules\Nodes\DIR`.
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
				$this->cache[$key] = $index = $this->index_construct();
			}
		}
		else
		{
			$index = $this->index_construct();
		}

		$this->descriptors = $index['descriptors'];

		foreach ($this->descriptors as $descriptor)
		{
			$namespace = $descriptor[Module::T_NAMESPACE];
			$constant = $namespace . '\DIR';

			if (!defined($constant))
			{
				define($constant, $descriptor[Module::T_PATH]);
			}
		}

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
	 * - (array) classes aliases: An array of _key/value_ pairs where _key_ is the alias of a class
	 * and _value_ if the real class.
	 * - (array) config constructors: An array of _key/value_ pairs where _key_ if the name of a
	 * config and _value_ is its constructor definition.
	 *
	 * @return array
	 */
	protected function index_construct()
	{
		$isolated_require = function ($__file__, $__exposed__)
		{
			extract($__exposed__);

			return require $__file__;
		};

		$descriptors = $this->paths ? $this->index_descriptors($this->paths) : array();
		$catalogs = array();
		$configs = array();
		$config_constructors = array();

		foreach ($descriptors as $id => &$descriptor)
		{
			$path = $descriptor[Module::T_PATH];

			if (is_dir($path . '/locale'))
			{
				$descriptor['__has_locale'] = true;
				$catalogs[] = $path;
			}

			if (is_dir($path . '/config'))
			{
				$descriptor['__has_config'] = true;
				$configs[] = $path;

				$core_config_path = $path . '/config/core.php';

				if (is_file($core_config_path))
				{
					$core_config = $isolated_require($core_config_path, array('path' => $path));

					if (isset($core_config['config constructors']))
					{
						$config_constructors += $core_config['config constructors'];
					}
				}
			}
		}

		return array
		(
			'descriptors' => $descriptors,
			'catalogs' => $catalogs,
			'configs' => $configs,
			'config constructors' => $config_constructors
		);
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
					throw new \InvalidArgumentException(format
					(
						'%var should be an array: %type given instead in %path', array
						(
							'var' => 'descriptor',
							'type' => gettype($descriptor),
							'path' => strip_root($descriptor_path)
						)
					));
				}

				if (empty($descriptor[Module::T_TITLE]))
				{
					throw new \InvalidArgumentException(format
					(
						'The %name value of the %id module descriptor is empty in %path.', array
						(
							'name' => Module::T_TITLE,
							'id' => $id,
							'path' => strip_root($descriptor_path)
						)
					));
				}

				if (empty($descriptor[Module::T_NAMESPACE]))
				{
					throw new \InvalidArgumentException(format
					(
						'%name is required. Invalid descriptor for module %id in %path.', array
						(
							'name' => Module::T_NAMESPACE,
							'id' => $id,
							'path' => strip_root($descriptor_path)
						)
					));
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

				$descriptor += array
				(
					Module::T_CATEGORY => null,
					Module::T_CLASS => $descriptor[Module::T_NAMESPACE] . '\Module',
					Module::T_DESCRIPTION => null,
					Module::T_DISABLED => empty($descriptor[Module::T_REQUIRED]),
					Module::T_EXTENDS => null,
					Module::T_ID => $id,
					Module::T_MODELS => array(),
					Module::T_PATH => $path,
					Module::T_PERMISSION => null,
					Module::T_PERMISSIONS => array(),
					Module::T_REQUIRED => false,
					Module::T_REQUIRES => array(),
					Module::T_VERSION => 'dev',
					Module::T_WEIGHT => 0,

					'__has_config' => false,
					'__has_locale' => false,
					'__parents' => array()
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
	 * @param array $descriptor Descriptor of the module to index.
	 *
	 * @return array The altered descriptor.
	 */
	protected function alter_descriptor(array $descriptor)
	{
		$id = $descriptor[Module::T_ID];
		$path = $descriptor[Module::T_PATH];
		$namespace = $descriptor[Module::T_NAMESPACE];

		# models and active records

		foreach ($descriptor[Module::T_MODELS] as $model_id => &$definition)
		{
			if (!is_array($definition))
			{
				throw new \InvalidArgumentException(format('Model definition is not an array, given: %value.', array('value' => $definition)));
			}

			$basename = $id;
			$separator_position = strrpos($basename, '.');

			if ($separator_position)
			{
				$basename = substr($basename, $separator_position + 1);
			}

			if (empty($definition[Model::NAME]))
			{
				$definition[Model::NAME] = Model::format_name($id, $model_id);
			}

			if (empty($definition[Model::CLASSNAME]))
			{
				$definition[Model::CLASSNAME] = $namespace . '\\' . ($model_id == 'primary' ? 'Model' : camelize(singularize($model_id)) . 'Model');
			}

			if (empty($definition[Model::ACTIVERECORD_CLASS]))
			{
				$definition[Model::ACTIVERECORD_CLASS] = $namespace . '\\' . camelize(singularize($model_id == 'primary' ? $basename : $model_id));
			}
		}

		return $descriptor;
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

		$this->index; // we make sure that the modules were indexed

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
	 * Returns the descriptors of the disabled modules.
	 *
	 * This method is the getter for the {@link $disabled_modules_descriptors} magic property.
	 *
	 * @return array
	 */
	protected function get_disabled_modules_descriptors()
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
	protected function get_enabled_modules_descriptors()
	{
		$this->sort_modules_descriptors();

		return $this->enabled_modules_descriptors;
	}

	/**
	 * Returns the paths of the enabled modules which have a `locale` folder.
	 *
	 * @return array[]string
	 */
	protected function get_locale_paths()
	{
		$paths = array();

		foreach ($this->enabled_modules_descriptors as $module_id => $descriptor)
		{
			if (!$descriptor['__has_locale'])
			{
				continue;
			}

			$paths[] = $descriptor[Module::T_PATH];
		}

		return $paths;
	}

	/**
	 * Returns the paths of the enabled modules which have a `config` folder.
	 *
	 * @return array[]string
	 */
	protected function get_config_paths()
	{
		$paths = array();

		foreach ($this->enabled_modules_descriptors as $module_id => $descriptor)
		{
			if (!$descriptor['__has_config'])
			{
				continue;
			}

			$paths[] = $descriptor[Module::T_PATH];
		}

		return $paths;
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

	/**
	 * Checks if a module extends another.
	 *
	 * @param string $module_id Module identifier.
	 * @param string $extending_id Identifier of the extended module.
	 *
	 * @return boolean `true` if the module extends the other.
	 */
	public function is_extending($module_id, $extending_id)
	{
		while ($module_id)
		{
			if ($module_id == $extending_id)
			{
				return true;
			}

			$descriptor = $this->descriptors[$module_id];

			$module_id = isset($descriptor[Module::T_EXTENDS]) ? $descriptor[Module::T_EXTENDS] : null;
		}

		return false;
	}
}

/*
 * EXCEPTIONS
 */

/**
 * This exception is thrown when a disabled module is requested.
 */
class ModuleIsDisabled extends \RuntimeException
{
	public function __construct($module_id, $code=500, \Exception $previous=null)
	{
		parent::__construct(format('Module is disabled: %module_id', array('module_id' => $module_id)), $code, $previous);
	}
}

/**
 * This exception is thrown when requested module is not defined.
 */
class ModuleNotDefined extends \RuntimeException
{
	public function __construct($module_id, $code=500, \Exception $previous=null)
	{
		parent::__construct(format('Module is not defined: %module_id', array('module_id' => $module_id)), $code, $previous);
	}
}