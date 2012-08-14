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

/**
 * Connections accessor.
 */
class Connections implements \ArrayAccess, \IteratorAggregate
{
	/**
	 * Connections definitions.
	 *
	 * @var array[string]array
	 */
	private $connections;

	/**
	 * Established connections.
	 *
	 * @var array[string]Database
	 */
	private $established = array();

	/**
	 * Constructor.
	 *
	 * @param array $connections Connections definitions usually come from the _core_ config.
	 */
	public function __construct(array $connections)
	{
		$this->connections = $connections;
	}

	/**
	 * Checks if a connection exists.
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($id)
	{
		return $this->connections[$id];
	}

	/**
	 * @see ArrayAccess::offsetSet()
	 *
	 * @throws Exception\OffsetNotWritable when an offset is set.
	 */
	public function offsetSet($offset, $value)
	{
		throw new Exception\OffsetNotWritable(array($offset, $this));
	}

	/**
	 * @see ArrayAccess::offsetUnset()
	 *
	 * @throws Exception\OffsetNotWritable when an offset is unset.
	 */
	public function offsetUnset($offset)
	{
		throw new Exception\OffsetNotWritable(array($offset, $this));
	}

	/**
	 * Gets a connection to the specified database.
	 *
	 * If the connection has not been established yet, it is created on the fly.
	 *
	 * Several connections may be defined.
	 *
	 * @param string $id The name of the connection to get.
	 *
	 * @return Database
	 *
	 * @throws Exception\OffsetNotReadable when the connection is not defined.
	 * @throws Database\ConnectionException when the connection failed.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($id)
	{
		if (isset($this->established[$id]))
		{
			return $this->established[$id];
		}

		if (!$this->offsetExists($id))
		{
			throw new Exception\OffsetNotReadable(format('The connection %id is not defined.', array('id' => $id)));
		}

		$options = $this->connections[$id] + array
		(
			'dsn' => null,
			'username' => 'root',
			'password' => null,
			'options' => array
			(
				Database::T_ID => $id
			)
		);

		#
		# we catch connection exceptions and rethrow them in order to avoid displaying sensible
		# information such as the username or password.
		#

		try
		{
			return $this->established[$id] = new Database($options['dsn'], $options['username'], $options['password'], $options['options']);
		}
		catch (\PDOException $e)
		{
			throw new Database\ConnectionException("Unable to establish database connection. The following message was returned: " . $e->getMessage(), 500, $e);
		}
	}

	/**
	 * Iterate through established connections.
	 *
	 * @return \ArrayIterator
	 *
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->established);
	}
}