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

use ICanBoogie\PropertyNotDefined;

/**
 * Connection to a databse.
 *
 * @property-read string $charset
 * @property-read string $collate
 * @property-read string $driver_name
 * @property-read string $id
 * @property-read string $table_name_prefix
 */
class Connection extends \PDO
{
	const T_ID = '#id';
	const T_TABLE_NAME_PREFIX = '#prefix';
	const T_CHARSET = '#charset';
	const T_COLLATE = '#collate';
	const T_TIMEZONE = '#timezone';

	/**
	 * Connection identifier.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Prefix to prepend to every table name.
	 *
	 * So if set to "dev", all table names will be named like "dev_nodes", "dev_contents", etc.
	 * This is a convenient way of creating a namespace for tables in a shared database.
	 * By default, the prefix is the empty string.
	 *
	 * @var string
	 */
	protected $table_name_prefix = '';

	/**
	 * Charset for the connection. Also used to specify the charset while creating tables.
	 *
	 * @var string
	 */
	protected $charset = 'utf8';

	/**
	 * Used to specify the collate while creating tables.
	 *
	 * @var string
	 */
	protected $collate = 'utf8_general_ci';

	/**
	 * Driver name for the connection.
	 *
	 * @var string
	 */
	protected $driver_name;

	/**
	 * The number of database queries and executions, used for statistics purpose.
	 *
	 * @var int
	 */
	public $queries_count = 0;

	/**
	 * The number of micro seconds spent per request.
	 *
	 * @var array[]array
	 */
	public $profiling = array();

	/**
	 * Creates a connection to a database.
	 *
	 * Custom options can be specified using the driver-specific connection options:
	 *
	 * - {@link T_ID}: Connection identifier.
	 * - {@link T_TABLE_NAME_PREFIX}: Prefix for the database tables.
	 * - {@link T_CHARSET} and {@link T_COLLATE}: Charset and collate used for the connection to the database,
	 * and to create tables.
	 * - {@link T_TIMEZONE}: Timezone for the connection.
	 *
	 * @link http://www.php.net/manual/en/pdo.construct.php
	 * @link http://dev.mysql.com/doc/refman/5.5/en/time-zone-support.html
	 *
	 * @param string $dsn
	 * @param string $username
	 * @param string $password
	 * @param array $options
	 */
	public function __construct($dsn, $username=null, $password=null, $options=array())
	{
		list($driver_name) = explode(':', $dsn, 2);

		$this->driver_name = $driver_name;

		$timezone = null;

		foreach ($options as $option => $value)
		{
			switch ($option)
			{
				case self::T_ID: $this->id = $value; break;
				case self::T_TABLE_NAME_PREFIX: $this->table_name_prefix = $value ? $value . '_' : null; break;
				case self::T_CHARSET: $this->charset = $value; $this->collate = null; break;
				case self::T_COLLATE: $this->collate = $value; break;
				case self::T_TIMEZONE: $timezone = $value; break;
			}
		}

		if ($driver_name == 'mysql')
		{
			$init_command = 'SET NAMES ' . $this->charset;

			if ($timezone)
			{
				$init_command .= ', time_zone = "' . $timezone . '"';
			}

			$options += array
			(
				self::MYSQL_ATTR_INIT_COMMAND => $init_command
			);
		}

		parent::__construct($dsn, $username, $password, $options);

		$this->setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
		$this->setAttribute(self::ATTR_STATEMENT_CLASS, array('ICanBoogie\ActiveRecord\Statement'));

		if ($driver_name == 'oci')
		{
			$this->exec("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD'");
		}
	}

	public function __get($property)
	{
		switch ($property)
		{
			case 'charset':
			case 'collate':
			case 'driver_name':
			case 'id':
			case 'table_name_prefix':
				return $this->$property;
			case 'prefix': // COMPAT-20120913
				return $this->table_name_prefix;
		}

		throw new PropertyNotDefined(array($property, $this));
	}

	/**
	 * Overrides the method to resolve the statement before it is prepared, then set its fetch
	 * mode and connection.
	 *
	 * @param string $statement Query statement.
	 * @param array $options
	 *
	 * @return Database\Statement The prepared statement.
	 *
	 * @throws Exception if the statement could not be prepared.
	 */
	public function prepare($statement, $options=array())
	{
		$statement = $this->resolve_statement($statement);

		try
		{
			$statement = parent::prepare($statement, $options);
		}
		catch (\PDOException $e)
		{
			$er = array_pad($this->errorInfo(), 3, '');

			throw new StatementInvalid(\ICanboogie\format
			(
				'SQL error: \1(\2) <code>\3</code> &mdash; <code>%query</code>', array
				(
					$er[0], $er[1], $er[2], '%query' => $statement
				)
			));
		}

		$statement->connection = $this;

		if (isset($options['mode']))
		{
			$mode = (array) $options['mode'];

			call_user_func_array(array($statement, 'setFetchMode'), $mode);
		}

		return $statement;
	}

	/**
	 * Overrides the method in order to prepare (and resolve) the statement and execute it with
	 * the specified arguments and options.
	 *
	 * @return Statement
	 */
	public function query($statement, array $args=array(), array $options=array())
	{
		$statement = $this->prepare($statement, $options);
		$statement->execute($args);

		return $statement;
	}

	/**
	 * Overrides the method to resolve the statement before actually execute it.
	 *
	 * The execution of the statement is wrapped in a try/catch block. If an exception of class
	 * \PDOException is caught, an exception of class {@link StatementInvalid} is thrown with additional
	 * information about the error.
	 *
	 * Using this method increments the `queries_by_connection` stat.
	 */
	public function exec($statement)
	{
		$statement = $this->resolve_statement($statement);

		try
		{
			$this->queries_count++;

			return parent::exec($statement);
		}
		catch (\PDOException $e)
		{
			$er = array_pad($this->errorInfo(), 3, '');

			throw new StatementInvalid(\ICanBoogie\format
			(
				'SQL error: \1(\2) <code>\3</code> &mdash; <code>\4</code>', array
				(
					$er[0], $er[1], $er[2], $statement
				)
			));
		}
	}

	/**
	 * Places quotes around the identifier.
	 *
	 * @param string|array $identifier
	 *
	 * @return string|array
	 */
	public function quote_identifier($identifier)
	{
		$quote = $this->driver_name == 'oci' ? '"' : '`';

		if (is_array($identifier))
		{
			return array_map
			(
				function($v) use ($quote)
				{
					return $quote . $v . $quote;
				},

				$identifier
			);
		}

		return $quote . $identifier . $quote;
	}

	/**
	 * Replaces placeholders with their value.
	 *
	 * The following placeholders are supported:
	 *
	 * - {prefix}: replaced by the {@link $table_name_prefix} property.
	 * - {charset}: replaced by the {@link $charset} property.
	 * - {collate}: replaced by the {@link $collate} property.
	 *
	 * @param string $statement
	 *
	 * @return string The resolved statement.
	 */
	public function resolve_statement($statement)
	{
		return strtr
		(
			$statement, array
			(
				'{prefix}' => $this->table_name_prefix,
				'{charset}' => $this->charset,
				'{collate}' => $this->collate
			)
		);
	}

	/**
	 * Alias for the `beginTransaction()` method.
	 *
	 * @see PDO::beginTransaction()
	 */
	public function begin()
	{
		return $this->beginTransaction();
	}

	/**
	 * Parses a schema to create a schema with low level definitions.
	 *
	 * For example, a column defined as 'serial' is parsed as :
	 *
	 * 'type' => 'integer', 'serial' => true, 'size' => 'big', 'unsigned' => true,
	 * 'primary' => true
	 *
	 * @param array $schema
	 *
	 * @return array
	 */
	public function parse_schema(array $schema)
	{
		$driver_name = $this->driver_name;

		$schema['primary'] = array();
		$schema['indexes'] = array();

		foreach ($schema['fields'] as $identifier => &$definition)
		{
			$definition = (array) $definition;

			#
			# translate special indexes to keys
			#

			if (isset($definition[0]))
			{
				$definition['type'] = $definition[0];

				unset($definition[0]);
			}

			if (isset($definition[1]))
			{
				$definition['size'] = $definition[1];

				unset($definition[1]);
			}

			#
			# handle special types
			#

			switch($definition['type'])
			{
				case 'serial':
				{
					$definition['type'] = 'integer';

					#
					# because auto increment only works on "INTEGER AUTO INCREMENT" in SQLite
					#

					if ($driver_name != 'sqlite')
					{
						$definition += array('size' => 'big', 'unsigned' => true);
					}

					$definition += array('auto increment' => true, 'primary' => true);
				}
				break;

				case 'foreign':
				{
					$definition['type'] = 'integer';

					if ($driver_name != 'sqlite')
					{
						$definition += array('size' => 'big', 'unsigned' => true);
					}

					$definition += array('indexed' => true);
				}
				break;

				case 'varchar':
				{
					$definition += array('size' => 255);
				}
				break;
			}

			#
			# primary
			#

			if (isset($definition['primary']) && !in_array($identifier, $schema['primary']))
			{
				$schema['primary'][] = $identifier;
			}

			#
			# indexed
			#

			if (!empty($definition['indexed']))
			{
				$index = $definition['indexed'];

				if (is_string($index))
				{
					if (isset($schema['indexes'][$index]) && in_array($identifier, $schema['indexes'][$index]))
					{
						# $identifier is already defined in $index
					}
					else
					{
						$schema['indexes'][$index][] = $identifier;
					}
				}
				else
				{
					if (!in_array($identifier, $schema['indexes']))
					{
						$schema['indexes'][$identifier] = $identifier;
					}
				}
			}
		}

		#
		# indexes that are part of the primary key are deleted
		#

		if ($schema['indexes'] && $schema['primary'])
		{
// 			echo "<h3>DIFF</h3>";

// 			var_dump($schema['primary'], $schema['indexes'], array_diff($schema['indexes'], $schema['primary']));

			$schema['indexes'] = array_diff($schema['indexes'], $schema['primary']);

			/*
			$primary = (array) $schema['primary'];

			foreach ($schema['indexes'] as $identifier => $dummy)
			{
				if (!in_array($identifier, $primary))
				{
					continue;
				}

				unset($schema['indexes'][$identifier]);
			}
			*/
		}

		if (count($schema['primary']) == 1)
		{
			$schema['primary'] = $schema['primary'][0];
		}

		return $schema;
	}

	/**
	 * Creates a table of the specified name and schema.
	 *
	 * @param string $unprefixed_name The unprefixed name of the table.
	 * @param array $schema The schema of the table.
	 *
	 * @return bool
	 *
	 * @throws Exception if the table could not be created.
	 */
	public function create_table($unprefixed_name, array $schema)
	{
		// FIXME-20091201: I don't think 'UNIQUE' is properly implemented

		$collate = $this->collate;
		$driver_name = $this->driver_name;

		$schema = $this->parse_schema($schema);

		$parts = array();

		foreach ($schema['fields'] as $identifier => $params)
		{
			$definition = '`' . $identifier . '`';

			$type = $params['type'];
			$size = isset($params['size']) ? $params['size'] : 0;

			switch ($type)
			{
				case 'blob':
				case 'char':
				case 'integer':
				case 'text':
				case 'varchar':
				case 'bit':
				{
					if ($size)
					{
						if (is_string($size))
						{
							$definition .= ' ' . strtoupper($size) . ($type == 'integer' ? 'INT' : $type);
						}
						else
						{
							$definition .= ' ' . $type . '(' . $size . ')';
						}
					}
					else
					{
						$definition .= ' ' . $type;
					}

					if (($type == 'integer') && !empty($params['unsigned']))
					{
						$definition .= ' UNSIGNED';
					}
				}
				break;

				case 'boolean':
				case 'date':
				case 'datetime':
				case 'time':
				case 'timestamp':
				case 'year':
				{
					$definition .= ' ' . $type;
				}
				break;

				case 'enum':
				{
					$enum = array();

					foreach ($size as $identifier)
					{
						$enum[] = '\'' . $identifier . '\'';
					}

					$definition .= ' ' . $type . '(' . implode(', ', $enum) . ')';
				}
				break;

				case 'double':
				case 'float':
				{
					$definition .= ' ' . $type;

					if ($size)
					{
						$definition .= '(' . implode(', ', (array) $size) . ')';
					}
				}
				break;

				default:
				{
					throw new \Exception(\ICanBoogie\format
					(
						'Unsupported type %type for row %identifier', array
						(
							'%type' => $type,
							'%identifier' => $identifier
						)
					));
				}
				break;
			}

			#
			# null
			#

			if (empty($params['null']))
			{
				$definition .= ' NOT NULL';
			}
			else
			{
				$definition .= ' NULL';
			}

			#
			# default
			#

			if (!empty($params['default']))
			{
				$default = $params['default'];

				$definition .= ' DEFAULT ' . ($default{strlen($default) - 1} == ')' ? $default : '"' . $default . '"');
			}

			#
			# serial, unique
			#

			if (!empty($params['auto increment']))
			{
				if ($driver_name == 'mysql')
				{
					$definition .= ' AUTO_INCREMENT';
				}
				else if ($driver_name == 'sqlite')
				{
// 					$definition .= ' PRIMARY KEY';
// 					unset($schema['primary']);
				}
			}
			else if (!empty($params['unique']))
			{
				$definition .= ' UNIQUE';
			}

			$parts[] = $definition;
		}

		#
		# primary key
		#

		if ($schema['primary'])
		{
			$keys = (array) $schema['primary'];

			$parts[] = 'PRIMARY KEY (' . implode(', ', $this->quote_identifier($keys)) . ')';
		}

		#
		# indexes
		#

		if (isset($schema['indexes']) && $driver_name == 'mysql')
		{
			foreach ($schema['indexes'] as $key => $identifiers)
			{
				$definition = 'INDEX ';

				if (!is_numeric($key))
				{
					$definition .= $this->quote_identifier($key) . ' ';
				}

				$definition .= '(' . implode(',', $this->quote_identifier((array) $identifiers)) . ')';

				$parts[] = $definition;
			}
		}

		$table_name = $this->prefix . $unprefixed_name;
		$statement = 'CREATE TABLE `' . $table_name . '` (' . implode(', ', $parts) . ')';

		if ($driver_name == 'mysql')
		{
			$statement .= ' CHARACTER SET ' . $this->charset . ' COLLATE ' . $this->collate;
		}

		$rc = ($this->exec($statement) !== false);

		if (!$rc)
		{
			return $rc;
		}

		if (isset($schema['indexes']) && $driver_name == 'sqlite')
		{
			#
			# SQLite: now that the table has been created, we can add indexes
			#

			foreach ($schema['indexes'] as $key => $identifiers)
			{
				$statement = 'CREATE INDEX `' . $key . '` ON ' . $table_name;

				$identifiers = (array) $identifiers;

				foreach ($identifiers as &$identifier)
				{
					$identifier = '`' . $identifier . '`';
				}

				$statement .= ' (' . implode(',', $identifiers) . ')';

				$this->exec($statement);
			}
		}

		return $rc;
	}

	/**
	 * Checks if a specified table exists in the database.
	 *
	 * @param string $unprefixed_name The unprefixed name of the table.
	 *
	 * @return bool true if the table exists, false otherwise.
	 */
	public function table_exists($unprefixed_name)
	{
		$name = $this->prefix . $unprefixed_name;

		if ($this->driver_name == 'sqlite')
		{
			$tables = $this->query('SELECT name FROM sqlite_master WHERE type = "table" AND name = ?', array($name))->fetchAll(self::FETCH_COLUMN);

			return !empty($tables);
		}
		else
		{
			$tables = $this->query('SHOW TABLES')->fetchAll(self::FETCH_COLUMN);

			return in_array($name, $tables);
		}

		return false;
	}

	/**
	 * Optimizes the tables of the database.
	 */
	public function optimize()
	{
		if ($this->driver_name == 'sqlite')
		{
			$this->exec('VACUUM');
		}
		else if ($this->driver_name == 'mysql')
		{
			$tables = $this->query('SHOW TABLES')->fetchAll(self::FETCH_COLUMN);

			$this->exec('OPTIMIZE TABLE ' . implode(', ', $tables));
		}
	}
}