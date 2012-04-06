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

use ICanBoogie\Exception;

/**
 * @property array $extended_schema Extended schema of the table.
 */
class DatabaseTable extends Object
{
	const T_ALIAS = 'alias';
	const T_CONNECTION = 'connection';
	const T_EXTENDS = 'extends';
	const T_IMPLEMENTS = 'implements';
	const T_NAME = 'name';
	const T_SCHEMA = 'schema';

	/**
	 * The model's connection to the database.
	 *
	 * @var WdDatabase A database connection.
	 */
	protected $connection;

	/**
	 * Name of the table, including the prefix defined by the model's connection.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Unprefixed version of the table's name.
	 *
	 * The "{self}" placeholder used in queries is replaced by the propertie's value.
	 *
	 * @var string
	 */
	protected $name_unprefixed;

	/**
	 * Primary key of the table, retrieved from the schema defined using the T_SCHEMA tag.
	 *
	 * @var mixed
	 */
	protected $primary;

	/**
	 * Alias for the table's name, which can be defined using the T_ALIAS tag or automatically created.
	 *
	 * The "{primary}" placeholder used in queries is replaced by the propertie's value.
	 *
	 * @var string
	 */
	protected $alias;

	/**
	 * Schema for the table.
	 *
	 * The "{alias}" placeholder used in queries is replaced by the propertie's value.
	 *
	 * @var array
	 */
	protected $schema;

	/**
	 * The parent is used when the table is in a hierarchy, which is the case if the table
	 * extends another table.
	 *
	 * @var WdDatabaseTable
	 */
	protected $parent;
	protected $implements = array();

	/**
	 * SQL fragment for the FROM clause of the query, made of the table's name and alias and those
	 * of the hierarchy.
	 *
	 * @var string
	 */
	protected $update_join;

	/**
	 * SQL fragment for the FROM clause of the query, made of the table's name and alias and those
	 * of the related tables, inherited and implemented.
	 *
	 * The "{self_and_related}" placeholder used in queries is replaced by the propertie's value.
	 *
	 * @var string
	 */
	protected $select_join;

	public function __construct($tags)
	{
		foreach ($tags as $tag => $value)
		{
			switch ($tag)
			{
				case self::T_ALIAS: $this->alias = $value; break;
				case self::T_CONNECTION: $this->connection = $value; break;
				case self::T_IMPLEMENTS: $this->implements = $value; break;
				case self::T_NAME: $this->name_unprefixed = $value; break;
				case self::T_SCHEMA: $this->schema = $value; break;
				case self::T_EXTENDS: $this->parent = $value; break;
			}
		}

		if (!$this->connection)
		{
			throw new Exception('The %tag tag is required', array('%tag' => 'T_CONNECTION'));
		}

		$this->name = $this->connection->prefix . $this->name_unprefixed;

		#
		# alias
		#

		if (!$this->alias)
		{
			$alias = $this->name_unprefixed;

			$pos = strrpos($alias, '_');

			if ($pos !== false)
			{
				$alias = substr($alias, $pos + 1);
			}

			if (substr($alias, -3, 3) == 'ies')
			{
				$alias = substr($alias, 0, -3) . 'y';
			}
			else if (substr($alias, -1, 1) == 's')
			{
				$alias = substr($alias, 0, -1);
			}

			$this->alias = $alias;
		}

		#
		# if we have a parent, we need to extend our fields with our parent primary key
		#

		$parent = $this->parent;

		if ($parent)
		{
			if (empty($this->schema['fields']))
			{
				throw new Exception('schema is empty for \1', array($this->name));
			}
			else
			{
				$primary = $parent->primary;
				$primary_definition = $parent->schema['fields'][$primary];

				unset($primary_definition['serial']);

				$this->schema['fields'] = array($primary => $primary_definition) + $this->schema['fields'];
			}

			#
			# implements are inherited too
			#

			if ($parent->implements)
			{
				$this->implements = array_merge($parent->implements, $this->implements);
			}
		}

		#
		# parse definition schema to have a complete schema
		#

		$this->schema = $this->connection->parse_schema($this->schema);

		#
		# retrieve primary key
		#

		if (!empty($this->schema['primary']))
		{
			$this->primary = $this->schema['primary'];
		}

		#
		# resolve inheritence and create a lovely _inner join_ string
		#

		$join = '';

		$parent = $this->parent;

		while ($parent)
		{
			$join .= "INNER JOIN `{$parent->name}` `{$parent->alias}` USING(`{$this->primary}`) ";

			$parent = $parent->parent;
		}

		$this->update_join = $join;

		$join = " `{$this->alias}` $join";

		#
		# resolve implements
		#

		if ($this->implements)
		{
			if (!is_array($this->implements))
			{
				throw new Exception('Expecting an array for T_IMPLEMENTS, given: \1', array($this->implements));
			}

			$i = 1;

			foreach ($this->implements as $implement)
			{
				if (!is_array($implement))
				{
					throw new Exception('Expecting array for implement: \1', array($implement));
				}

				$table = $implement['table'];

				if (!($table instanceof DatabaseTable))
				{
					throw new Exception('Implement must be an instane of ICanBoogie\DatabaseTable: \1', array(get_class($table)));
				}

				$name = $table->name;
				$primary = $table->primary;

				$join .= empty($implement['loose']) ? 'INNER' : 'LEFT';
				$join .= " JOIN `$name` as {$table->alias} USING(`$primary`) ";

				$i++;
			}
		}

		$this->select_join = $join;
	}

	protected function __volatile_get_connection()
	{
		return $this->connection;
	}

	protected function __set_connection()
	{
		throw new Exception('The %property property cannot be set', array('%property' => 'connection'));
	}

	protected function __volatile_get_primary()
	{
		return $this->primary;
	}

	protected function __set_primary()
	{
		throw new Exception('The %property property cannot be set', array('%property' => 'primary'));
	}

	/*
	**

	INSTALL

	**
	*/

	public function install()
	{
		if (!$this->schema)
		{
			throw new Exception('Missing schema to install table %name', array('%name' => $this->name_unprefixed));
		}

		return $this->connection->create_table($this->name_unprefixed, $this->schema);
	}

	public function uninstall()
	{
		return $this->drop();
	}

	/*
	 * Checks whether the table is installed.
	 *
	 * @return bool true if the table exists, false otherwise.
	 */
	public function is_installed()
	{
		return $this->connection->table_exists($this->name_unprefixed);
	}

	/**
	 * Getter for the {@link $extended_schema} property.
	 */
	protected function __get_extended_schema()
	{
		$table = $this;
		$schemas = array();

		while ($table)
		{
			$schemas[] = $table->schema;

			$table = $table->parent;
		}

		$schema = call_user_func_array('\ICanBoogie\array_merge_recursive', $schemas);

		$this->connection->parse_schema($schema);

		return $schema;
	}

	/**
	 * Resolve statement placeholders.
	 *
	 * The following placeholder are replaced :
	 *
	 * - `{alias}`: The alias of the table.
	 * - `{prefix}`: The prefix used for the tables of the connection.
	 * - `{primary}`: The primary key of the table.
	 * - `{self}`: The name of the table.
	 * - `{self_and_related}`: The escaped name of the table and the SELECT and JOIN clauses.
	 *
	 * @param string $statement The statement to resolve.
	 *
	 * @return string
	 */
	public function resolve_statement($statement)
	{
		return strtr
		(
			$statement, array
			(
				'{alias}' => $this->alias,
				'{prefix}' => $this->connection->prefix,
				'{primary}' => $this->primary,
				'{self}' => $this->name,
				'{self_and_related}' => "`$this->name` $this->select_join"
			)
		);
	}

	/**
	 * Interface to the connection's prepare method.
	 *
	 * @return WdDatabaseStatement
	 */

	public function prepare($query, $options=array())
	{
		$query = $this->resolve_statement($query);

		return $this->connection->prepare($query, $options);
	}

	public function quote($string, $parameter_type=\PDO::PARAM_STR)
	{
		return $this->connection->quote($string, $parameter_type);
	}

	public function execute($query, array $args=array(), array $options=array())
	{
		$statement = $this->prepare($query, $options);

		return $statement->execute($args);
	}

	/**
	 * Interface to the connection's query() method.
	 *
	 * The statement is resolved using the resolve_statement() method and prepared.
	 *
	 * @param string $query
	 * @param array $args
	 * @param array $options
	 *
	 * @return WdDatabaseStatement
	 */

	public function query($query, array $args=array(), array $options=array())
	{
		$query = $this->resolve_statement($query);

		$statement = $this->prepare($query, $options);
		$statement->execute($args);

		return $statement;
	}

	protected function filter_values(array $values, $extended=false)
	{
		$filtered = array();
		$holders = array();
		$identifiers = array();

		$schema = $extended ? $this->extended_schema : $this->schema;
		$fields = $schema['fields'];

		foreach ($values as $identifier => $value)
		{
			if (!array_key_exists($identifier, $fields))
			{
				continue;
			}

			$filtered[] = $value;
			$holders[$identifier] = '`' . $identifier . '` = ?';
			$identifiers[] = '`' . $identifier . '`';
		}

		return array($filtered, $holders, $identifiers);
	}

	public function save(array $values, $id=null, array $options=array())
	{
		if ($id)
		{
			return $this->update($values, $id) ? $id : false;
		}

		return $this->save_callback($values, $id, $options);
	}

	protected function save_callback(array $values, $id=null, array $options=array())
	{
		if ($id)
		{
			$this->update($values, $id);

			return $id;
		}

		if (empty($this->schema['fields']))
		{
			throw new Exception('Missing fields in schema');
		}

		$parent_id = 0;

		if ($this->parent)
		{
			$parent_id = $this->parent->save_callback($values, $id, $options);

			if (!$parent_id)
			{
				throw new Exception('Parent save failed: \1 returning \2', array((string) $this->parent->name, $parent_id));
			}
		}

		$driver_name = $this->connection->driver_name;

		list($filtered, $holders) = $this->filter_values($values);

		// FIXME: ALL THIS NEED REWRITE !

		if ($holders)
		{
			// faire attention à l'id, si l'on revient du parent qui a inséré, on doit insérer aussi, avec son id

			if ($id)
			{
				$filtered[] = $id;

				$statement = 'UPDATE {self} SET ' . implode(', ', $holders) . ' WHERE `{primary}` = ?';

				$statement = $this->prepare($statement);

				$rc = $statement->execute($filtered);
			}
			else
			{
				if ($driver_name == 'mysql')
				{
					if ($parent_id && empty($holders[$this->primary]))
					{
						$filtered[] = $parent_id;
						$holders[] = '`{primary}` = ?';
					}

					$statement = 'INSERT INTO {self} SET ' . implode(', ', $holders);

					$statement = $this->prepare($statement);

					$rc = $statement->execute($filtered);
				}
				else if ($driver_name == 'sqlite')
				{
					$rc = $this->insert($values, $options);
				}
			}
		}
		else if ($parent_id && !$id)
		{
			#
			# a new entry has been created, but we don't have any other fields then the primary key
			#

			if (empty($holders[$this->primary]))
			{
				$filtered[] = $parent_id;
				$holders[] = '`{primary}` = ?';
			}

			$statement = 'INSERT INTO {self} SET ' . implode(', ', $holders);

			$statement = $this->prepare($statement);

			$rc = $statement->execute($filtered);
		}
		else
		{
			$rc = true;
		}

		if ($parent_id)
		{
			return $parent_id;
		}

		if (!$rc)
		{
			return false;
		}

		if (!$id)
		{
			$id = $this->connection->lastInsertId();
		}

		return $id;
	}

	public function insert(array $values, array $options=array())
	{
		list($values, $holders, $identifiers) = $this->filter_values($values);

		if (!$values)
		{
			return;
		}

		$driver_name = $this->connection->driver_name;

		$on_duplicate = isset($options['on duplicate']) ? $options['on duplicate'] : null;

		if ($driver_name == 'mysql')
		{
			$query = 'INSERT';

			if (!empty($options['ignore']))
			{
				$query .= ' IGNORE ';
			}

			$query .= ' INTO `{self}` SET ' . implode(', ', $holders);

			if ($on_duplicate)
			{
				if ($on_duplicate === true)
				{
					#
					# if 'on duplicate' is true, we use the same input values, but we take care of
					# removing the primary key and its corresponding value
					#

					$update_values = array_combine(array_keys($holders), $values);
					$update_holders = $holders;

					$primary = $this->primary;

					if (is_array($primary))
					{
						$flip = array_flip($primary);

						$update_holders = array_diff_key($update_holders, $flip);
						$update_values = array_diff_key($update_values, $flip);
					}
					else
					{
						unset($update_holders[$primary]);
						unset($update_values[$primary]);
					}

					$update_values = array_values($update_values);
				}
				else
				{
					list($update_values, $update_holders) = $this->filter_values($on_duplicate);
				}

				$query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update_holders);

				$values = array_merge($values, $update_values);
			}
		}
		else if ($driver_name == 'sqlite')
		{
			$holders = array_fill(0, count($identifiers), '?');

			$query = 'INSERT' . ($on_duplicate ? ' OR REPLACE' : '') . ' INTO `{self}` (' . implode(', ', $identifiers) . ') VALUES (' . implode(', ', $holders) . ')';
		}

		return $this->execute($query, $values);
	}

	/**
	 * Update the values of an entry.
	 *
	 * Even if the entry is spread over multiple tables, all the tables are updated in a single
	 * step.
	 *
	 * @param array $values
	 * @param mixed $key
	 */

	public function update(array $values, $key)
	{
		list($values, $holders) = $this->filter_values($values, true);

		$query = 'UPDATE `{self}` ' . $this->update_join . ' SET ' . implode(', ', $holders) . ' WHERE `{primary}` = ?';
		$values[] = $key;

		return $this->execute($query, $values);
	}

	/*
	**

	DELETE & TRUNCATE

	TODO: move delete() to WdModel

	**
	*/

	public function delete($key)
	{
		if ($this->parent)
		{
			$this->parent->delete($key);
		}

		$where = 'where ';

		if (is_array($this->primary))
		{
			$parts = array();

			foreach ($this->primary as $identifier)
			{
				$parts[] = '`' . $identifier . '` = ?';
			}

			$where .= implode(' and ', $parts);
		}
		else
		{
			$where .= '`{primary}` = ?';
		}

		return $this->execute
		(
			'delete from `{self}` ' . $where, (array) $key
		);
	}

	// FIXME-20081223: what about extends ?

	public function truncate()
	{
		if ($this->connection->driver_name == 'sqlite')
		{
			$rc = $this->execute('delete from {self}');

			$this->execute('vacuum');

			return $rc;
		}

		return $this->execute('truncate table `{self}`');
	}

	public function drop(array $options=array())
	{
		$query = 'drop table ';

		if (!empty($options['if exists']))
		{
			$query .= 'if exists ';
		}

		$query .= '`{self}`';

		return $this->execute($query);
	}
}