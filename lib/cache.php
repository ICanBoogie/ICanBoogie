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

interface StorageInterface
{
	public function store($key, $data, $ttl=0)
	{

	}

	public function retrieve($key)
	{

	}

	public function eliminate($key)
	{

	}
}

class Cache implements StorageInterface
{
	private $master_key;

	public function __construct()
	{
		$this->master_key = md5($_SERVER['DOCUMENT_ROOT']);
	}

	public function store($key, $data, $ttl=0)
	{
		apc_store($key, $data, $ttl);
	}

	public function retrieve($key)
	{
		$rc = apc_fetch($key, $success);

		return $success ? $rc : null;
	}
}