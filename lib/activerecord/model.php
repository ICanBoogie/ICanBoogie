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

use ICanBoogie\Exception;
use ICanBoogie\Module;
use ICanBoogie\OffsetNotWritable;
use ICanBoogie\PropertyNotWritable;

/**
 * Base class for activerecord models.
 *
 * @property-read string $activerecord_class Class of the active records of the model.
 * @property-read int $count The number of records of the model.
 * @property-read bool $exists Whether the SQL table associated with the model exists.
 * @property-read string $id The identifier of the model.
 */
class Model extends Table implements \ArrayAccess
{
	const T_CLASS = 'class';
	const T_ACTIVERECORD_CLASS = 'activerecord-class';
	const T_ID = 'id';

	/**
	 * @var string Name of the class to use to created active record instances.
	 */
	protected $activerecord_class;

	protected $attributes;

	/**
	 * Override the constructor to provide support for the {@link T_ACTIVERECORD_CLASS} tag and
	 * extended support for the {@link T_EXTENDS} tag.
	 *
	 * If {@link T_EXTENDS} is defined but the model has no schema ({@link T_SCHEMA} is empty),
	 * the name of the model and the schema are inherited from the extended model and
	 * {@link T_EXTENDS} is set to the parent model object. If {@link T_ACTIVERECORD_CLASS} is
	 * empty, its value is set to the extended model's active record class.
	 *
	 * If {@link T_ACTIVERECORD_CLASS} is set, its value is saved in the
	 * {@link $activerecord_class} property.
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
				$tags[self::T_ACTIVERECORD_CLASS] = $extends->activerecord_class;
			}
		}

		if (empty($tags[self::T_ID]))
		{
			$tags[self::T_ID] = $tags[self::T_NAME];
		}

		parent::__construct($tags);

		#
		# Resolve the active record class.
		#

		if ($this->parent)
		{
			$this->activerecord_class = $this->parent->activerecord_class;
		}

		if (isset($tags[self::T_ACTIVERECORD_CLASS]))
		{
			$this->activerecord_class = $tags[self::T_ACTIVERECORD_CLASS];
		}

		$this->attributes = $tags;
	}

	/**
	 * Overrides the method to handle dynamic finders and scopes.
	 *
	 * @see Object::__call()
	 */
	public function __call($method, $arguments)
	{
		if (strpos($method, 'filter_by_') === 0)
		{
			$arq = new Query($this);

			return call_user_func_array(array($arq, $method), $arguments);
		}

		$callback = 'scope_' . $method;

		if (method_exists($this, $callback))
		{
			$arq = new Query($this);

			return call_user_func_array(array($arq, $method), $arguments);
		}

		return parent::__call($method, $arguments);
	}

	/**
	 * Overrides the method to handle scopes.
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

	/**
	 * Returns the identifier of the model.
	 *
	 * @return string
	 */
	protected function volatile_get_id()
	{
		return $this->attributes[self::T_ID];
	}

	/**
	 * @throws PropertyNotWritable in appenpt to write {@link $id}
	 */
	protected function volatile_set_id()
	{
		throw new PropertyNotWritable(array('id', $this));
	}

	/**
	 * Returns the class of the active records of the model.
	 *
	 * @return string
	 */
	protected function volatile_get_activerecord_class()
	{
		return $this->activerecord_class;
	}

	/**
	 * Finds a record or a collection of records.
	 *
	 * @param mixed $key A key or an array of keys.
	 *
	 * @throws RecordNotFound when the record, or one or more records of the records
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
					throw new RecordNotFound
					(
						\ICanBoogie\format('Records %keys do not exists in model %model.', array
						(
							'%model' => $this->name_unprefixed,
							'%keys' => implode(', ', array_keys($missing))
						)),

						$records
					);
				}
				else
				{
					$key = array_keys($missing);
					$key = array_shift($key);

					throw new RecordNotFound
					(
						\ICanBoogie\format('Record %key does not exists in model %model.', array
						(
							'%model' => $this->name_unprefixed,
							'%key' => $key
						)),

						$records
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
				throw new RecordNotFound
				(
					\ICanBoogie\format('Record %key does not exists in model %model.', array
					(
						'%model' => $this->name_unprefixed,
						'%key' => $key
					)),

					array($key => null)
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
	 * @see ICanBoogie\ActiveRecord\Table::save($properies, $key, $options)
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
	protected function store(\ICanBoogie\ActiveRecord $record)
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
	 * @return ActiveRecord|null Returns the active record found in the cache or null if it wasn't
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

	static private $master_cache_key;

	/**
	 * Delegation hub.
	 *
	 * @return mixed
	 */
	private function delegate_to_query()
	{
		$trace = debug_backtrace(false);
		$arq = new Query($this);

		return call_user_func_array(array($arq, $trace[1]['function']), $trace[1]['args']);
	}

	/**
	 * The method is delegated to {@link Query::joins}.
	 *
	 * @param string $expression
	 *
	 * @return Query
	 *
	 * @see Query::joins
	 */
	public function joins($expression)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated {@link Query::select}.
	 *
	 * @param string $expression
	 *
	 * @return Query
	 *
	 * @see Query::select
	 */
	public function select($expression)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::where}.
	 *
	 * @param $conditions
	 *
	 * @param null $conditions_args
	 *
	 * @return Query
	 *
	 * @see Query::where
	 */
	public function where($conditions, $conditions_args=null)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::group}.
	 *
	 * @param $group
	 *
	 * @return Query
	 *
	 * @see Query::group
	 */
	public function group($group)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::order}.
	 *
	 * @param $order
	 *
	 * @return Query
	 */
	public function order($order)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::limit}.
	 *
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return Query
	 *
	 * @see Query::limit
	 */
	public function limit($limit, $offset=null)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::exists}.
	 *
	 * @param $key
	 *
	 * @return bool
	 *
	 * @see Query::exists
	 */
	public function exists($key=null)
	{
		return $this->delegate_to_query();
	}

	/**
	 * @throws PropertyNotWritable in attempt to write {@link $exists}
	 */
	protected function volatile_set_exists()
	{
		throw new PropertyNotWritable(array('exists', $this));
	}

	/**
	 * Checks that the SQL table associated with the model exists.
	 *
	 * @return bool
	 */
	protected function volatile_get_exists()
	{
		return $this->exists();
	}

	/**
	 * The method is delegated to {@link Query::count}.
	 *
	 * @param $column
	 *
	 * @return Query
	 *
	 * @see Query::count
	 */
	public function count($column=null)
	{
		return $this->delegate_to_query();
	}

	/**
	 * @throws PropertyNotWritable in attempt to write {@link $count}
	 */
	protected function volatile_set_count()
	{
		throw new PropertyNotWritable(array('count', $this));
	}

	/**
	 * Returns the number of records of the model.
	 *
	 * @return int
	 */
	protected function volatile_get_count()
	{
		return $this->count();
	}

	/**
	 * The method is delegated to {@link Query::average}.
	 *
	 * @param $column
	 *
	 * @return Query
	 *
	 * @see Query::average
	 */
	public function average($column)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::minimum}.
	 *
	 * @param $column
	 *
	 * @return Query
	 *
	 * @see Query::minimum
	 */
	public function minimum($column)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::maximum}.
	 *
	 * @param $column
	 *
	 * @return Query
	 *
	 * @see Query::maximum
	 */
	public function maximum($column)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::sum}.
	 *
	 * @param $column
	 *
	 * @return Query
	 *
	 * @see Query::sum
	 */
	public function sum($column)
	{
		return $this->delegate_to_query();
	}

	/**
	 * The method is delegated to {@link Query::all}.
	 *
	 * @return array An array of records.
	 */
	public function all()
	{
		return $this->delegate_to_query();
	}

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
	 * Calls a given scope on the active record query specified in the scope_args.
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
			throw new ScopeNotDefined($scope_name, $this);
		}

		return call_user_func_array(array($this, $callback), $scope_args);
	}

	/*
	 * ArrayAccess implementation
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
		throw new OffsetNotWritable(array($offset, $this));
	}

	/**
	 * Checks if the record identified by the given key exists.
	 *
	 * @see \ArrayAccess::offsetExists()
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
	 * Alias for the {@link find()} method.
	 *
	 * @see Model::find()
	 */
	public function offsetGet($key)
	{
		return $this->find($key);
	}

	/**
	 * Formats a SQL table name given the module id and the model id.
	 *
	 * @param string $module_id
	 * @param string $model_id
	 *
	 * @return string
	 */
	static public function format_name($module_id, $model_id='primary')
	{
		return strtr($module_id, '.', '_') . ($model_id == 'primary' ? '' : '__' . $model_id);
	}
}

/**
 * Raised when Active Record cannot find record by given id or set of ids.
 *
 * @property-read array[int]ActiveRecord|null $records
 */
class RecordNotFound extends ActiveRecordException
{
	/**
	 * A key/value array where keys are the identifier of the record, and the value is the result
	 * of finding the record. If the record was found the value is a {@link ActiveRecord}
	 * object, otherwise the `null` value.
	 *
	 * @var array[int]ActiveRecord|null
	 */
	private $records;

	/**
	 * Initializes the {@link $records} property.
	 *
	 * @param string $message
	 * @param array $records
	 * @param int $code Defaults to 404.
	 * @param \Exception $previous Previous exception.
	 */
	public function __construct($message, array $records, $code=404, \Exception $previous=null)
	{
		$this->records = $records;

		parent::__construct($message, $code, $previous);
	}

	public function __get($property)
	{
		switch ($property)
		{
			case 'records': return $this->records;
		}
	}
}

/**
 * Raised when a requested scope is not defined.
 *
 * @property-read string $scope_name
 * @property-read Model $model
 */
class ScopeNotDefined extends ActiveRecordException
{
	/**
	 * Name of the scope.
	 *
	 * @var string
	 */
	private $scope_name;

	/**
	 * Model on which the scope was invoked.
	 *
	 * @var Model
	 */
	private $model;

	/**
	 * Initializes the {@link $scope_name} and {@link $model} properties.
	 *
	 * @param string $scope_name Name of the scope.
	 * @param Model $model Model on which the scope was invoked.
	 * @param int $code Default to 404.
	 * @param \Exception $previous Previous exception.
	 */
	public function __construct($scope_name, Model $model, $code=404, \Exception $previous)
	{
		$this->scope_name = $scope_name;
		$this->model = $model;

		parent::__construct(\ICanBoogie\format('Unknown scope %scope for model %model', array('%scope' => $scope_name, '%model' => $model->name_unprefixed)), $code, $previous);
	}

	public function __get($property)
	{
		switch ($property)
		{
			case 'scope_name': return $this->scope_name;
			case 'model': return $this->model;
		}
	}
}