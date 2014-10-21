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
 * A storage using APC.
 */
class APCStorage implements StorageInterface
{
	private $master_key;

	public function __construct()
	{
		$this->master_key = md5($_SERVER['DOCUMENT_ROOT']);
	}

	public function store($key, $data, $ttl=0)
	{
		apc_store($this->master_key . $key, $data, $ttl);
	}

	public function retrieve($key)
	{
		$rc = apc_fetch($this->master_key . $key, $success);

		return $success ? $rc : null;
	}

	public function eliminate($key)
	{
		apc_delete($this->master_key . $key);
	}

	public function clear()
	{
		apc_clear_cache();
	}

	public function exists($key)
	{
		return apc_exists($this->master_key . $key);
	}
}
