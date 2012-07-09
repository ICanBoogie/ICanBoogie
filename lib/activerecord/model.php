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

use ICanBoogie\Module;
use ICanBoogie\Exception;
use ICanBoogie\ActiveRecord;
use ICanBoogie\ActiveRecord\Query;

class Model extends \ICanBoogie\DatabaseTable implements \ArrayAccess
{
	const T_CLASS = 'class';
	const T_ACTIVERECORD_CLASS = 'activerecord-class';
	const T_ID = 'id';

	/**
	 * @var string Name of the class to use to created activerecord instances.
	 */
	public $ar_class;

	protected $attributes;

	/**
	 * Override the constructor to provide support for the {@link T_ACTIVERECORD_CLASS} tag and
	 * extended support for the {@link T_EXTENDS} tag.
	 *
	 * If {@link T_EXTENDS} is defined but the model has no schema ({@link T_SCHEMA} is empty),
	 * the name of the model and the schema are inherited from the extended model and
	 * {@link T_EXTENDS} is set to the parent model object. If {@link T_ACTIVERECORD_CLASS} is
	 * empty, its value is set to the extended model's activerecord class.
	 *
	 * If {@link T_ACTIVERECORD_CLASS} is set, its value is saved in the `ar_class` property.
	 *
	 * @param array $tags Tags used to construct the model.
	 */
	public function __construct(array $tags)
	{
		if (isset($tags[self::T_EXTENDS]) && empty($tags[self::T_SCHEMA]))
		{
			$extends = $tags[self::T_EXTENDS];

			$tags[self::T_NAME] = $extends->name_unprefixed;
			$tags[self::T_SCHEMA] = $extends->schema;
			$tags[self::T_EXTENDS] = $extends->parent;

			if (empty($tags[self::T_ACTIVERECORD_CLASS]))
			{
				$tags[self::T_ACTIVERECORD_CLASS] = $extends->ar_class;
			}
		}

		parent::__construct($tags);

		#
		# Resolve the active record class.
		#

		if ($this->parent)
		{
			$this->ar_class = $this->parent->ar_class;
		}

		if (isset($tags[self::T_ACTIVERECORD_CLASS]))
		{
			$this->ar_class = $tags[self::T_ACTIVERECORD_CLASS];
		}

		$this->attributes = $tags;
	}

	/**
	 * Override the method to handle dynamic finders.
	 *
	 * @see Object::__call()
	 */
	public function __call($method, $arguments)
	{
		if (preg_match('#^find_by_#', $method))
		{
			$arq = new Query($this);

			return call_user_func_array(array($arq, $method), $arguments);
		}

		return parent::__call($method, $arguments);
	}

	/**
	 * Override the method to handle scopes.
	 */
	public function __get($property)
	{
		$callback = 'scope_' . $property;

		if (method_exists($this, $callback))
		{
			$arq = new Query($this);

			return $this->$callback($arq);
		}

		return parent::__get($property);
	}

	protected function volatile_get_id()
	{
		return $this->attributes[self::T_ID];
	}

	protected function volatile_set_id()
	{
		throw new Exception\PropertyNotWritable(array('id', $this));
	}

	/**
	 * Finds a record or a collection of records.
	 *
	 * @param mixed $key A key or an array of keys.
	 *
	 * @throws Exception\MissingRecord when the record, or one or more records of the records
	 * set, could not be found.
	 *
	 * @return ActiveRecord|array A record or a set of records.
	 */
	public function find($key)
	{
		if (func_num_args() > 1)
		{
			$key = func_get_args();
		}

		if (is_array($key))
		{
			$records = array_combine($key, array_fill(0, count($key), null));
			$missing = $records;

			foreach ($records as $key => $dummy)
			{
				$record = $this->retrieve($key);

				if (!$record)
				{
					continue;
				}

				$records[$key] = $record;
				unset($missing[$key]);
			}

			if ($missing)
			{
				$primary = $this->primary;
				$query_records = $this->where(array($primary => array_keys($missing)))->all;

				foreach ($query_records as $record)
				{
					$key = $record->$primary;
					$records[$key] = $record;
					unset($missing[$key]);

					$this->store($record);
				}
			}

			if ($missing)
			{
				if (count($missing) > 1)
				{
					throw new Exception\MissingRecord
					(
						'Records %keys do not exists in model %model.', array
						(
							'%model' => $this->name_unprefixed,
							'%keys' => implode(', ', array_keys($missing))
						),

						404, null, $records
					);
				}
				else
				{
					$key = array_keys($missing);
					$key = array_shift($key);

					throw new Exception\MissingRecord
					(
						'Record %key does not exists in model %model.', array
						(
							'%model' => $this->name_unprefixed,
							'%key' => $key
						),

						404, null, $records
					);
				}
			}

			return $records;
		}

		$record = $this->retrieve($key);

		if ($record === null)
		{
			$record = $this->where(array($this->primary => $key))->one;

			if (!$record)
			{
				throw new Exception\MissingRecord
				(
					'Record %key does not exists in model %model.', array
					(
						'%model' => $this->name_unprefixed,
						'%key' => $key
					),

					404, null, array($key => null)
				);
			}

			$this->store($record);
		}

		return $record;
	}

	/**
	 * Because records are cached, we need to removed the record from the cache when it is saved,
	 * so that loading the record again returns the updated record, not the one in the cache.
	 *
	 * @see ICanBoogie.DatabaseTable::save($properies, $key, $options)
	 */
	public function save(array $properties, $key=null, array $options=array())
	{
		if ($key)
		{
			$this->eliminate($key);
		}

		return parent::save($properties, $key, $options);
	}

	static protected $cached_records;

	/**
	 * Stores a record in the records cache.
	 *
	 * @param ActiveRecord $record The record to store.
	 */
	protected function store(ActiveRecord $record)
	{
		$key = $this->create_cache_key($record->{$this->primary});

		if (!$key || isset(self::$cached_records[$key]))
		{
			return;
		}

		self::$cached_records[$key] = $record;

		if (\ICanBoogie\CACHE_ACTIVERECORDS)
		{
			apc_store($key, $record, 3600);
		}
	}

	/**
	 * Retrieves a record from the records cache.
	 *
	 * @param int $key
	 *
	 * @return ActiveRecord|null Returns the activerecord found in the cache or null if it wasn't
	 * there.
	 */
	protected function retrieve($key)
	{
		$key = $this->create_cache_key($key);

		if (!$key)
		{
			return;
		}

		$record = null;

		if (isset(self::$cached_records[$key]))
		{
			$record = self::$cached_records[$key];
		}
		else if (\ICanBoogie\CACHE_ACTIVERECORDS)
		{
			$record = apc_fetch($key, $success);

			if ($success)
			{
				self::$cached_records[$key] = $record;
			}
			else
			{
				$record = null;
			}
		}

		return $record;
	}

	/**
	 * Eliminates an object from the cache.
	 *
	 * @param int $key
	 */
	protected function eliminate($key)
	{
		$key = $this->create_cache_key($key);

		if (!$key)
		{
			return;
		}

		if (\ICanBoogie\CACHE_ACTIVERECORDS)
		{
			apc_delete($key);
		}

		self::$cached_records[$key] = null;
	}

	/**
	 * Creates a unique cache key.
	 *
	 * @param int $key
	 *
	 * @return string A unique cache key.
	 */
	protected function create_cache_key($key)
	{
		if ($key === null)
		{
			return;
		}

		if (self::$master_cache_key === null)
		{
			self::$master_cache_key = md5($_SERVER['DOCUMENT_ROOT']) . '/AR/';
		}

		return self::$master_cache_key . $this->connection->id . '/' . $this->name . '/' . $key;
	}

	private static $master_cache_key;

	/**
	 * Delegation hub.
	 *
	 * @return mixed
	 */
	private function defer_to_actionrecord_query()
	{
		$trace = debug_backtrace(false);
		$arq = new Query($this);

		return call_user_func_array(array($arq, $trace[1]['function']), $trace[1]['args']);
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::joins method.
	 *
	 * @return Query
	 */
	public function joins($expression)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::select method.
	 *
	 * @return Query
	 */
	public function select($expression)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::where method.
	 *
	 * @return Query
	 */
	public function where($conditions, $conditions_args=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::group method.
	 *
	 * @return Query
	 */
	public function group($group)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::order method.
	 *
	 * @return Query
	 */
	public function order($order)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::limit method.
	 *
	 * @return Query
	 */
	public function limit($limit, $offset=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::exists method.
	 *
	 * @return Query
	 */
	public function exists($key=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	protected function volatile_get_exists()
	{
		return $this->exists();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::count method.
	 *
	 * @return Query
	 */
	public function count($column=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	protected function volatile_get_count()
	{
		return $this->count();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::average method.
	 *
	 * @return Query
	 */
	public function average($column)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::minimum method.
	 *
	 * @return Query
	 */
	public function minimum($column)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::maximum method.
	 *
	 * @return Query
	 */
	public function maximum($column)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::sum method.
	 *
	 * @return Query
	 */
	public function sum($column)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the ICanBoogie\ActiveRecord\Query::all method.
	 *
	 * @return array An array of records.
	 */
	public function all()
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation getter for the ICanBoogie\ActiveRecord\Query::all getter.
	 *
	 * @return array An array of records.
	 */
	protected function volatile_get_all()
	{
		return $this->all();
	}

	/**
	 * Checks if the model has a given scope.
	 *
	 * Scopes are defined using method with the "scope_" prefix. As an example, the `visible`
	 * scope can be defined by implementing the `scope_visible` method.
	 *
	 * @param string $name Scope name.
	 *
	 * @return boolean
	 */
	public function has_scope($name)
	{
		return method_exists($this, 'scope_' . $name);
	}

	/**
	 * Calls a given scope on the activerecord query specified in the scope_args.
	 *
	 * @param string $scope_name Name of the scope to apply to the query.
	 * @param array $scope_args Arguments to forward to the scope method.
	 *
	 * @throws Exception when the specified scope is not defined.
	 *
	 * @return Query
	 */
	public function scope($scope_name, $scope_args=null)
	{
		$callback = 'scope_' . $scope_name;

		if (!method_exists($this, $callback))
		{
			throw new Exception('Unknown scope %scope for model %model', array('%scope' => $scope_name, '%model' => $this->name_unprefixed));
		}

		return call_user_func_array(array($this, $callback), $scope_args);
	}

	/*
	 * ArrayAcces implementation
	 */

	/**
	 * Offsets are not settable.
	 *
	 * @throws OffsetNotWritable when one tries to write an offset.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($offset, $value)
	{
		throw new Exception\OffsetNotWritable(array($offset, $this));
	}

	/**
	 * Checks if the record identified by the given key exists.
	 *
	 * @see \ArrayAccess::offsetExists()
	 *
	 * @return bool true is the record exists, false otherwise.
	 */
	public function offsetExists($key)
	{
		return $this->exists($key);
	}

	/**
	 * Deletes the record specified by the given key.
	 *
	 * @see \ArrayAccess::offsetUnset()
	 * @see Model::delete();
	 */
	public function offsetUnset($key)
	{
		$this->delete($key);
	}

	/**
	 * Returns the record corresponding to the given key.
	 *
	 * @see \ArrayAccess::offsetGet()
	 * @see Model::find();
	 *
	 * @return ActiveRecord
	 */
	public function offsetGet($key)
	{
		return $this->find($key);
	}

	static public function is_extending($tags, $instanceof)
	{
		if (is_string($tags))
		{
			\ICanBoogie\log('is_extending is not competent with string references: \1', array($tags));

			return true;
		}

		// TODO-2010630: The method should handle submodels to, not just 'primary'

		if (empty($tags[self::T_EXTENDS]))
		{
			return false;
		}

		$extends = $tags[self::T_EXTENDS];

		if ($extends == $instanceof)
		{
			return true;
		}

		global $core;

		if (empty($core->modules->descriptors[$extends][Module::T_MODELS]['primary']))
		{
			return false;
		}

		$tags = $core->modules->descriptors[$extends][Module::T_MODELS]['primary'];

		return self::is_extending($tags, $instanceof);
	}

	/**
	 * Resolves the name of a model giving its module id and model id.
	 *
	 * @param string $namespace Namespace of the module defining the model.
	 * @param string $model_id The model id.
	 *
	 * @return string The resolved class name.
	 */
	public static function resolve_class_name($namespace, $model_id='primary')
	{
		return $namespace . '\\' . ($model_id == 'primary' ? '' : \ICanBoogie\normalize_namespace_part($model_id)) . 'Model';
	}

	/**
	 * Formats a SQL table name given the module id and the model id.
	 *
	 * @param string $module_id
	 * @param string $model_id
	 *
	 * @return string
	 */
	public static function format_name($module_id, $model_id='primary')
	{
		return strtr($module_id, '.', '_') . ($model_id == 'primary' ? '' : '__' . $model_id);
	}
}

namespace ICanBoogie\Exception;

class MissingRecord extends \ICanBoogie\Exception
{
	public $rc;

	public function __construct($message, array $params=array(), $code=500, $previous=null, $rc)
	{
		$this->rc = $rc;

		parent::__construct($message, $params, $code, $previous);
	}
}