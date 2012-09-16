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

use ICanBoogie\PropertyNotFound;

/**
 * A database statement.
 *
 * @property-read array $all
 * @property-read mixed $one
 * @property-read string $rc
 */
class Statement extends \PDOStatement
{
	/**
	 * The database connection that created this statement.
	 *
	 * @var Connection
	 */
	public $connection;

	/**
	 * Alias of {@link execute()}.
	 *
	 * The arguments can be provided as an array or a list of arguments.
	 */
	public function __invoke()
	{
		$args = func_get_args();

		if ($args && is_array($args[0]))
		{
			$args = $args[0];
		}

		return $this->execute($args);
	}

	/**
	 * Dispatch magic properties `all` and `one`.
	 *
	 * @param string $property
	 *
	 * @return mixed
	 *
	 * @throws PropertyNotFound if one tries to get a property that is not supported.
	 */
	public function __get($property)
	{
		switch ($property)
		{
			case 'all': return $this->fetchAll();
			case 'one': return $this->fetchAndClose();
			case 'rc': return $this->fetchColumnAndClose();
		}

		throw new PropertyNotFound(array($property, $this));
	}

	/**
	 * Executes the statement and increments the connection queries count.
	 *
	 * @throws StatementInvalid if the execution of the statement failed.
	 *
	 * @see \PDOStatement::execute()
	 */
	public function execute($args=array())
	{
		$start = microtime(true);

		if (!empty($this->connection))
		{
			$this->connection->queries_count++;
		}

		try
		{
			$this->connection->profiling[] = array(microtime(true) - $start, $this->queryString . ' ' . json_encode($args));

			return parent::execute($args);
		}
		catch (\PDOException $e)
		{
			$er = array_pad($this->errorInfo(), 3, '');

			throw new StatementInvalid(\ICanBoogie\format
			(
				'SQL error: \1(\2) <code>\3</code> &mdash; <code>%query</code>\5', array
				(
					$er[0], $er[1], $er[2], '%query' => $this->queryString, $args
				)
			), $e->getCode(), $e);
		}
	}

	/**
	 * Fetches the first row of the result set and closes the cursor.
	 *
	 * @param int $fetch_style[optional]
	 * @param int $cursor_orientation[optional]
	 * @param int $cursor_offset[optional]
	 *
	 * @return mixed
	 *
	 * @see PDOStatement::fetch()
	 */
	public function fetchAndClose($fetch_style=\PDO::FETCH_BOTH, $cursor_orientation=\PDO::FETCH_ORI_NEXT, $cursor_offset=0)
	{
		$args = func_get_args();
		$rc = call_user_func_array(array($this, 'parent::fetch'), $args);

		$this->closeCursor();

		return $rc;
	}

	/**
	 * Fetches a column of the first row of the result set and closes the cursor.
	 *
	 * @param int $column_number
	 *
	 * @return string
	 *
	 * @see PDOStatement::fetchColumn()
	 */
	public function fetchColumnAndClose($column_number=0)
	{
		$rc = parent::fetchColumn($column_number);

		$this->closeCursor();

		return $rc;
	}

	/**
	 * Returns an array containing all of the result set rows (FETCH_LAZY supported)
	 *
	 * @param int $fetch_style
	 * @param mixed $fetch_argument[optional]
	 * @param array $ctor_args[optional]
	 *
	 * @return array
	 */
	public function fetchGroups($fetch_style, $fetch_argument=null, array $ctor_args=array())
	{
		$args = func_get_args();
		$rc = array();

		if($fetch_style === \PDO::FETCH_LAZY)
		{
			call_user_func_array(array($this, 'setFetchMode'), $args);

			foreach($this as $row)
			{
				$rc[$row[0]][] = $row;
			}

			return $rc;
		}

		$args[0] = \PDO::FETCH_GROUP | $fetch_style;

		$rc = call_user_func_array(array($this, 'parent::fetchAll'), $args);

		return $rc;
	}

	/**
	 * Alias for {@link \PDOStatement::fetchAll()}
	 */
	public function all($fetch_style=null, $column_index=null, array $ctor_args=null)
	{
		return call_user_func_array(array($this, 'fetchAll'), func_get_args());
	}
}

/**
 * Exception thrown when an statement execution failed because of an error.
 */
class StatementInvalid extends ActiveRecordException
{

}