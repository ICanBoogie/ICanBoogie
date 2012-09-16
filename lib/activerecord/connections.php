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

use ICanBoogie\OffsetNotWritable;

/**
 * Connection manager.
 *
 * @property-read array[string]array $definitions Connection definitions.
 * @property-read Database $established Established connections.
 */
class Connections implements \ArrayAccess, \IteratorAggregate
{
	/**
	 * Connection definitions.
	 *
	 * @var array[string]array
	 */
	private $definitions;

	/**
	 * Established connections.
	 *
	 * @var array[string]Database
	 */
	private $established = array();

	/**
	 * Initialize the {@link $definitions} property.
	 *
	 * @param array $definitions Connection definitions.
	 */
	public function __construct(array $definitions)
	{
		$this->definitions = $definitions;
	}

	/**
	 * Returns the read-only properties {@link $definitions} and {@link $established}.
	 *
	 * @param string $property
	 *
	 * @return mixed
	 */
	public function __get($property)
	{
		switch ($property)
		{
			case 'definitions': return $this->definitions;
			case 'established': return $this->established;
		}
	}

	/**
	 * Checks if a connection definition exists.
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($id)
	{
		return isset($this->definitions[$id]);
	}

	/**
	 * Sets the definition of a connection.
	 *
	 * @throws ConnectionAlreadyEstablished in attempt to set the definition of an already
	 * established connection.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($id, $definition)
	{
		if (isset($this->established[$id]))
		{
			throw new ConnectionAlreadyEstablished($id);
		}

		$this->definitions[$id] = $definition;
	}

	/**
	 * @throws OffsetNotWritable in attempt to unset offset.
	 */
	public function offsetUnset($offset)
	{
		throw new OffsetNotWritable(array($offset, $this));
	}

	/**
	 * Returns a connection to the specified database.
	 *
	 * If the connection has not been established yet, it is created on the fly.
	 *
	 * @param string $id The name of the connection to get.
	 *
	 * @return Database
	 *
	 * @throws ConnectionNotDefined when the connection requested is not defined.
	 * @throws ConnectionNotEstablished when the connection failed.
	 */
	public function offsetGet($id)
	{
		if (isset($this->established[$id]))
		{
			return $this->established[$id];
		}

		if (!$this->offsetExists($id))
		{
			throw new ConnectionNotDefined($id);
		}

		$options = $this->definitions[$id] + array
		(
			'dsn' => null,
			'username' => 'root',
			'password' => null,
			'options' => array
			(
				Connection::T_ID => $id
			)
		);

		#
		# we catch connection exceptions and rethrow them in order to avoid displaying sensible
		# information such as the username or password.
		#

		try
		{
			return $this->established[$id] = new Connection($options['dsn'], $options['username'], $options['password'], $options['options']);
		}
		catch (\PDOException $e)
		{
			throw new ConnectionNotEstablished("Connection not established: " . $e->getMessage() . ".", 500, $e);
		}
	}

	/**
	 * Returns an iterator for established connections.
	 *
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->established);
	}
}

/*
 * EXCEPTIONS
 */

/**
 * Exception is thrown when connection to the database could not been established.
 */
class ConnectionNotDefined extends ActiveRecordException
{
	public function __construct($id, $code=500, \Exception $previous)
	{
		parent::__construct("Connection not defined: {$id}.", $code, $previous);
	}
}

/**
 * Exception is thrown when connection to the database could not been established.
 */
class ConnectionNotEstablished extends ActiveRecordException
{

}

/**
 * Exception is thrown in attempt to set the definition of an already established connection.
 */
class ConnectionAlreadyEstablished extends ActiveRecordException
{
	public function __construct($id, $code=500, \Exception $previous)
	{
		parent::__construct("Connection already established: {$id}.", $code, $previous);
	}
}