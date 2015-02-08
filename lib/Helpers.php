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
 * Patchable helpers of the ICanBoogie package.
 */
class Helpers
{
	static private $jumptable = [

		'generate_token' => [ __CLASS__, 'generate_token' ],
		'pbkdf2' => [ __CLASS__, 'pbkdf2' ]

	];

	/**
	 * Calls the callback of a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param array $arguments Arguments.
	 *
	 * @return mixed
	 */
	static public function __callstatic($name, array $arguments)
	{
		return call_user_func_array(self::$jumptable[$name], $arguments);
	}

	/**
	 * Patches a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param callable $callback Callback.
	 *
	 * @throws \RuntimeException is attempt to patch an undefined function.
	 */
	static public function patch($name, $callback)
	{
		if (empty(self::$jumptable[$name]))
		{
			throw new \RuntimeException("Undefined patchable: $name.");
		}

		self::$jumptable[$name] = $callback;
	}

	/*
	 * Default implementations
	 */

	static private function generate_token($length=8, $possible=TOKEN_NARROW)
	{
		$token = '';
		$y = strlen($possible) - 1;

		while ($length--)
		{
			$i = mt_rand(0, $y);
			$token .= $possible[$i];
		}

		return $token;
	}

	static private function pbkdf2($p, $s, $c=1000, $kl=32, $a='sha256')
	{
		$hl = strlen(hash($a, null, true)); # Hash length
		$kb = ceil($kl / $hl); # Key blocks to compute
		$dk = ''; # Derived key

		# Create key
		for ($block = 1 ; $block <= $kb ; $block++)
		{
			# Initial hash for this block
			$ib = $b = hash_hmac($a, $s . pack('N', $block), $p, true);
			# Perform block iterations
			for ( $i = 1; $i < $c; $i ++ )
			# XOR each iterate
			$ib ^= ($b = hash_hmac($a, $b, $p, true));
			$dk .= $ib; # Append iterated block
		}

		# Return derived key of correct length
		return substr($dk, 0, $kl);
	}
}
