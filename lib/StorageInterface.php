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
 * An interface for classes implementing storage capabilities.
 */
interface StorageInterface
{
	/**
	 * Cache a variable in the cache.
	 *
	 * @param string $key Store the variable using this name. keys are cache-unique, so storing
	 * a second value with the same key will overwrite the original value.
	 * @param mixed $value The value to store.
	 * @param string $ttl Time To Live; store `value` in the cache for `ttl` seconds. After the
	 * `ttl` has passed, the stored value won't be available for the next request. If no `ttl` is
	 * supplied (or if the `ttl` is empty), the value will persist until it is removed from the
	 * cache manually, or otherwise fails to exist in the cache.
	 */
	public function store($key, $value, $ttl=null);

	/**
	 * Retrieve a stored value from the cache.
	 *
	 * @param string $key
	 *
	 * @return mixed|null The value associated with the key, or `null` if the key doesn't exists.
	 */
	public function retrieve($key);

	/**
	 * Remove a stored variable from the cache.
	 *
	 * @param string $key
	 */
	public function eliminate($key);

	/**
	 * Clear the cache.
	 */
	public function clear();

	/**
	 * Check if a cache key exists.
	 *
	 * @param string $key
	 *
	 * @return bool `true` if the key exists, `false` otherwise.
	 */
	public function exists($key);
}
