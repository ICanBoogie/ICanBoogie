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

use ICanBoogie\Object;
use ICanBoogie\ActiveRecord;

/**
 * This class is used to query models, it helps to compose SQL queries and offers a lot of features
 * to ease the process. Most query related methods of the Model class create a Query object that
 * is return for further specification such as conditions or limits.
 *
 * @property array $all An array with all the records matching the query.
 * @property mixed $one Returns only the first record matching the query.
 * @property array $pairs Returns an array of key/value pairs.
 * @property array $rc Returns the first column of the first row matching the query.
 * @property int $count The number of records matching the query.
 * @property bool|array $exists true if a record matching the query exists, false otherwise. If
 * there is multiple records, the property is an array of booleans.
 *
 * @see http://dev.mysql.com/doc/refman/5.6/en/select.html
 */
class Query extends Object implements \IteratorAggregate
{
	protected $select;
	protected $join;

	protected $conditions = array();
	protected $conditions_args = array();

	protected $group;
	protected $order;
	protected $having;
	protected $having_args = array();

	protected $offset;
	protected $limit;

	protected $mode;

	protected $model;

	/**
	 * Constructor.
	 *
	 * @param Model $model The model to query.
	 */
	public function __construct(Model $model)
	{
		$this->model = $model;
	}

	/**
	 * Override the method to handle magic 'find_by_' methods.
	 *
	 * @see ICanBoogie.Object::__call()
	 */
	public function __call($method, $arguments)
	{
		if (strpos($method, 'find_by_') === 0)
		{
			return $this->defered_dynamic_finder(substr($method, 8), $arguments); // 8 is for: strlen('find_by_')
		}

		return parent::__call($method, $arguments);
	}

	public function __toString()
	{
		$rc = 'SELECT ' . ($this->select ? $this->select : '*') . ' FROM {self_and_related}' . $this->build();
		$rc = $this->model->resolve_statement($rc);

		return $rc;
	}

	// TODO-20110420: cache available model scope names by model class

	/**
	 * Override the method to handle scopes.
	 *
	 * @see ICanBoogie.Object::find_method_callback()
	 */
	protected function find_method_callback($method)
	{
		if (strpos($method, '__volatile_get_') === 0)
		{
			if ($this->model->has_scope(substr($method, 15))) // 15 is for: strlen('__volatile_get_')
			{
				return array($this, '__scope');
			}
		}

		return parent::find_method_callback($method);
	}

	/**
	 * This is the method callback for scopes.
	 *
	 * @return Query
	 */
	protected function __scope()
	{
		$args = func_get_args();

		$query = array_shift($args);
		$scope_name = array_shift($args);
		array_unshift($args, $query);

		return call_user_func(array($this->model, 'scope'), $scope_name, $args);
	}

	/**
	 * Defines the SELECT clause.
	 *
	 * @param string $expression The expression of the SELECT clause. e.g. 'nid, title'.
	 *
	 * @return Query
	 */
	public function select($expression)
	{
		$this->select = $expression;

		return $this;
	}

	/**
	 * Adds a JOIN clause.
	 *
	 * @param string $expression The expression can be a full JOIN clause or a reference to a
	 * model defined as ":<model_id>" e.g. ":nodes".
	 *
	 * @return Query
	 */
	public function joins($expression)
	{
		global $core;

		if ($expression{0} == ':')
		{
			$model = $core->models[substr($expression, 1)];
			$expression = $model->resolve_statement('INNER JOIN `{self}` AS `{alias}` USING(`{primary}`)');
		}

		$this->join .= ' ' . $expression;

		return $this;
	}

	/**
	 * Parses the conditions for the {@link where()} and {@link having()} methods.
	 *
	 * @return array An array made of the condition string and its arguments.
	 */
	private function defered_parse_conditions()
	{
		global $core;

		$trace = debug_backtrace(false);
		$args = $trace[1]['args'];

		$conditions = array_shift($args);

		if (is_array($conditions))
		{
			$c = '';
			$conditions_args = array();

			foreach ($conditions as $column => $arg)
			{
				if (is_array($arg))
				{
					$joined = '';

					foreach ($arg as $value)
					{
						$joined .= ',' . (is_numeric($value) ? $value : $this->model->quote($value));
					}

					$joined = substr($joined, 1);

					$c .= ' AND `' . ($column{0} == '!' ? substr($column, 1) . '` NOT' : $column . '`') . ' IN(' . $joined . ')';
				}
				else
				{
					$conditions_args[] = $arg;

					$c .= ' AND `' . ($column{0} == '!' ? substr($column, 1) . '` !' : $column . '` ') . '= ?';
				}
			}

			$conditions = substr($c, 5);
		}
		else
		{
			$conditions_args = array();

			if ($args)
			{
				if (is_array($args[0]))
				{
					$conditions_args = $args[0];
				}
				else
				{
					#
					# We dereference values otherwise the caller would get a corrupted array.
					#

					foreach ($args as $key => $value)
					{
						$conditions_args[$key] = $value;
					}
				}
			}
		}

		return array($conditions ? '(' . $conditions . ')' : null, $conditions_args);
	}

	private function defered_dynamic_finder($finder, array $conditions_args=array())
	{
		$conditions = explode('_and_', $finder);

		return $this->where(array_combine($conditions, $conditions_args));
	}

	/**
	 * Add conditions to the SQL statement.
	 *
	 * Conditions can either be specified as string or array.
	 *
	 * 1. Pure string conditions
	 *
	 * If you'de like to add conditions to your statement, you could just specify them in there,
	 * just like `$model->where('order_count = 2');`. This will find all the entries, where the
	 * `order_count` field's value is 2.
	 *
	 * 2. Array conditions
	 *
	 * Now what if that number could vary, say as an argument from somewhere, or perhaps from the
	 * user’s level status somewhere? The find then becomes something like:
	 *
	 * `$model->where('order_count = ?', 2);`
	 *
	 * or
	 *
	 * `$model->where(array('order_count' => 2));`
	 *
	 * Or if you want to specify two conditions, you can do it like:
	 *
	 * `$model->where('order_count = ? AND locked = ?', 2, false);`
	 *
	 * or
	 *
	 * `$model->where(array('order_count' => 2, 'locked' => false));`
	 *
	 * Or if you want to specify subset conditions:
	 *
	 * `$model->where(array('order_id' => array(123, 456, 789)));`
	 *
	 * This will return the orders with the `order_id` 123, 456 or 789.
	 *
	 * 3. Modifiers
	 *
	 * When using the "identifier" => "value" notation, you can switch the comparison method by
	 * prefixing the identifier with a bang "!"
	 *
	 * `$model->where(array('!order_id' => array(123, 456, 789)));`
	 *
	 * This will return the orders with the `order_id` different than 123, 456 and 789.
	 *
	 * `$model->where(array('!order_count' => 2);`
	 *
	 * This will return the orders with the `order_count` different than 2.
	 *
	 * @param mixed $conditions
	 * @param mixed $conditions_args
	 *
	 * @return Query
	 */
	public function where($conditions, $conditions_args=null)
	{
		list($conditions, $conditions_args) = $this->defered_parse_conditions();

		if ($conditions)
		{
			$this->conditions[] = $conditions;

			if ($conditions_args)
			{
				$this->conditions_args = array_merge($this->conditions_args, $conditions_args);
			}
		}

		return $this;
	}

	/**
	 * Defines the ORDER clause.
	 *
	 * @param string The order for the ORDER clause e.g. 'weight, date DESC'.
	 *
	 * @return Query
	 */
	public function order($order)
	{
		$this->order = $order;

		return $this;
	}

	/**
	 * Defines the GROUP clause.
	 *
	 * @return Query
	 */
	public function group($group)
	{
		$this->group = $group;

		return $this;
	}

	/**
	 * Defines the HAVING clause.
	 *
	 * @return Query
	 */
	public function having($conditions, $conditions_args=null)
	{
		list($having, $having_args) = $this->defered_parse_conditions();

		$this->having = $having;
		$this->having_args = $having_args;

		return $this;
	}

	/**
	 * Defines the offset of the LIMIT clause.
	 *
	 * @return Query
	 */
	public function offset($offset)
	{
		$this->offset = (int) $offset;

		return $this;
	}

	/**
	 * Apply the limit and/or offset to the SQL fired.
	 *
	 * You can use the limit to specify the number of records to be retrieved, ad use the offset to
	 * specifythe number of records to skip before starting to return records:
	 *
	 * `$model->limit(10);`
	 *
	 * Will return a maximum of 10 clients and because ti specifies no offset it will return the
	 * first 10 in the table.
	 *
	 * `$model->limit(5, 10);`
	 *
	 * Will return a maximum of 10 clients beginning with the 5th.
	 *
	 * @param int $limit
	 *
	 * @return Query
	 */
	public function limit($limit)
	{
		$offset = null;

		if (func_num_args() == 2)
		{
			$offset = $limit;
			$limit = func_get_arg(1);
		}

		$this->offset = (int) $offset;
		$this->limit = (int) $limit;

		return $this;
	}

	/**
	 * Set the fetch mode for the query.
	 *
	 * @param mixed $mode
	 *
	 * @see http://www.php.net/manual/en/pdostatement.setfetchmode.php
	 */
	public function mode($mode)
	{
		$this->mode = func_get_args();
	}

	/**
	 * Builds the query except for the SELECT and FROM parts.
	 *
	 * @return string The query as a string.
	 */
	protected function build()
	{
		$query = '';

		if ($this->join)
		{
			$query .= ' ' . $this->join;
		}

		if ($this->conditions)
		{
			$query .= ' WHERE ' . implode(' AND ', $this->conditions);
		}

		if ($this->group)
		{
			$query .= ' GROUP BY ' . $this->group;

			if ($this->having)
			{
				$query .= ' HAVING ' . $this->having;
			}
		}

		if ($this->order)
		{
			$query .= ' ORDER BY ' . $this->order;
		}

		if ($this->offset && $this->limit)
		{
			$query .= " LIMIT $this->offset, $this->limit";
		}
		else if ($this->offset)
		{
			$query .= " LIMIT $this->offset, 18446744073709551615";
		}
		else if ($this->limit)
		{
			$query .= " LIMIT $this->limit";
		}

		return $query;
	}

	/**
	 * Prepares the query.
	 *
	 * We use the connection's prepare() method because the statement has already been resolved
	 * during the __toString() method and we don't want for the statement to be parsed twice.
	 *
	 * @return DatabaseStatement
	 */
	protected function prepare()
	{
		return $this->model->connection->prepare((string) $this);
	}

	/**
	 * Prepares and executes the query.
	 *
	 * @return DatabaseStatement
	 */
	public function query()
	{
		$statement = $this->prepare();
		$statement->execute(array_merge($this->conditions_args, $this->having_args));

		return $statement;
	}

	/**
	 * Returns a prepared query.
	 *
	 * @return DatabaseStatement
	 */
	protected function __volatile_get_prepared()
	{
		return $this->prepare();
	}

	/*
	 * FINISHER
	 */

	private function resolve_fetch_mode()
	{
		$trace = debug_backtrace(false);

		if ($trace[1]['args'])
		{
			$args = $trace[1]['args'];
		}
		else if ($this->mode)
		{
			$args = $this->mode;
		}
		else if ($this->select)
		{
			$args = array(\PDO::FETCH_ASSOC);
		}
		else if ($this->model->ar_class)
		{
			$args = array(\PDO::FETCH_CLASS, $this->model->ar_class, array($this->model));
		}
		else
		{
			$args = array(\PDO::FETCH_CLASS, 'ICanBoogie\ActiveRecord', array($this->model));
		}

		return $args;
	}

	/**
	 * Executes the query and returns an array of records.
	 *
	 * @return array
	 */
	public function all()
	{
		$statement = $this->query();
		$args = $this->resolve_fetch_mode();

		return call_user_func_array(array($statement, 'fetchAll'), $args);
	}

	/**
	 * Getter for the {@link $all} magic property.
	 *
	 * @return array
	 *
	 * @see all()
	 */
	protected function __volatile_get_all()
	{
		return $this->all();
	}

	/**
	 * Returns the first result of the query and close the cursor.
	 *
	 * @return mixed The return value of this function on success depends on the fetch mode. In
	 * all cases, FALSE is returned on failure.
	 */
	public function one()
	{
		$previous_limit = $this->limit;

		$this->limit = 1;

		$statement = $this->query();

		$this->limit = $previous_limit;

		$args = $this->resolve_fetch_mode();

		if (count($args) > 1 && $args[0] == \PDO::FETCH_CLASS)
		{
			array_shift($args);

			$rc = call_user_func_array(array($statement, 'fetchObject'), $args);

			$statement->closeCursor();

			return $rc;
		}

		return call_user_func_array(array($statement, 'fetchAndClose'), $args);
	}

	/**
	 * Getter for the {@link $one} magic property.
	 *
	 * @return mixed
	 *
	 * @see one()
	 */
	protected function __volatile_get_one()
	{
		return $this->one();
	}

	/**
	 * Execute que query and returns an array of key/value pairs, where the key is the value of
	 * the first column and the value of the key the value of the second column.
	 *
	 * @return array
	 */
	protected function __volatile_get_pairs()
	{
		return $this->all(\PDO::FETCH_KEY_PAIR);
	}

	/**
	 * Returns the value of the first column of the first row.
	 *
	 * @return string
	 */
	protected function __volatile_get_rc()
	{
		$previous_limit = $this->limit;

		$this->limit = 1;

		$statement = $this->query();

		$this->limit = $previous_limit;

		return $statement->fetchColumnAndClose();
	}

	/**
	 * Checks the existence of records in the model.
	 *
	 * $model->exists;
	 * $model->where('name = "max"')->exists;
	 * $model->exists(1);
	 * $model->exists(1, 2);
	 * $model->exists(array(1, 2));
	 *
	 * @param mixed $key
	 *
	 * @return bool|array
	 */
	public function exists($key=null)
	{
		$suffix = '';

		if ($key !== null)
		{
			if (func_num_args() > 1)
			{
				$key = func_get_args();
			}

			$this->where(array('{primary}' => $key));
		}
		else if (!$this->limit)
		{
			$suffix = ' LIMIT 1';
		}

		$rc = $this->model->query('SELECT `{primary}` FROM {self_and_related}' . $this->build() . $suffix, array_merge($this->conditions_args, $this->having_args))->fetchAll(\PDO::FETCH_COLUMN);

		if (is_array($key))
		{
			if ($rc)
			{
				$rc = array_combine($rc, array_fill(0, count($rc), true)) + array_combine($key, array_fill(0, count($key), false));
			}
		}
		else
		{
			$rc = !empty($rc);
		}

		return $rc;
	}

	/**
	 * Getter for the {@link $exists} magic property.
	 *
	 * @return bool|array
	 *
	 * @see exists()
	 */
	protected function __volatile_get_exists()
	{
		return $this->exists();
	}

	/*
	 * Calculations
	 */

	/**
	 * Defered method for all the computationnal methods.
	 *
	 * @param string $method
	 * @param string $column
	 *
	 * @return string|array
	 */
	private function compute($method, $column)
	{
		$query = 'SELECT ';

		if ($column)
		{
			if ($method == 'COUNT')
			{
				$query .= "`$column`, $method(`$column`)";

				$this->group($column);
			}
			else
			{
				$query .= "$method(`$column`)";
			}
		}
		else
		{
			$query .= $method . '(*)';
		}

		$query .= ' AS count FROM {self_and_related}' . $this->build();
		$query = $this->model->query($query, array_merge($this->conditions_args, $this->having_args));

		if ($method == 'COUNT' && $column)
		{
			return $query->fetchAll(\PDO::FETCH_KEY_PAIR);
		}

		return $query->fetchColumnAndClose();
	}

	/**
	 * Implements the 'COUNT' computation.
	 */
	public function count($column=null)
	{
		return $this->compute('COUNT', $column);
	}

	/**
	 * Getter for the {@link $count} magic property.
	 *
	 * @return int
	 */
	protected function __volatile_get_count()
	{
		return $this->count();
	}

	/**
	 * Implements the 'AVG' computation.
	 *
	 * @param string $column
	 */
	public function average($column)
	{
		return $this->compute('AVG', $column);
	}

	/**
	 * Implements the 'MIN' computation.
	 *
	 * @param string $column
	 */
	public function minimum($column)
	{
		return $this->compute('MIN', $column);
	}

	/**
	 * Implements the 'MAX' computation.
	 *
	 * @param string $column
	 */
	public function maximum($column)
	{
		return $this->compute('MAX', $column);
	}

	/**
	 * Implements the 'SUM' computation.
	 *
	 * @param string $column
	 */
	public function sum($column)
	{
		return $this->compute('SUM', $column);
	}

	/**
	 * Deletes the records matching the conditions and limits of the query.
	 *
	 * @return mixed The result of the operation.
	 */
	public function delete()
	{
		$query = 'DELETE FROM {self} ' . $this->build();

		return $this->model->execute($query, $this->conditions_args);
	}

	/**
	 * Returns an iterator for the query.
	 *
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->all());
	}
}