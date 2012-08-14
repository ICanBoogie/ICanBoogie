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

// http://en.wikipedia.org/wiki/Key_strengthening
// http://en.wikipedia.org/wiki/Key_derivation_function
// http://en.wikipedia.org/wiki/PBKDF2

class Security
{
	/** PBKDF2 Implementation (described in RFC 2898)
	 *
	 *  @param string $p password
	 *  @param string $s salt
	 *  @param int $c iteration count (use 1000 or higher)
	 *  @param int $kl derived key length
	 *  @param string $a hash algorithm
	 *
	 *  @return string derived key
	 *
	 *  @source http://www.itnewb.com/v/Encrypting-Passwords-with-PHP-for-Storage-Using-the-RSA-PBKDF2-Standard
	 */
	static public function pbkdf2($p, $s, $c=1000, $kl=32, $a='sha256')
	{
		$hl = strlen(hash($a, null, true)); # Hash length
		$kb = ceil($kl / $hl); # Key blocks to compute
		$dk = ''; # Derived key

		# Create key
		for ( $block = 1; $block <= $kb; $block ++ )
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

	static public $password_characters = array
	(
		'narrow' => '$=@#23456789bcdfghjkmnpqrstvwxyz',
		'medium' => '$=@#23456789bcdfghjkmnpqrstvwxyzBCDFGHJKMNPQRSTVWXYZ',
		'wide' => '!"#$%&()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~'
	);

	/**
	 * Generate a password.
	 *
	 * @param int $length The length of the password. Default: 8
	 * @param string $possible The characters that can be used to create the password.
	 * If you defined your own, pay attention to ambiguous characters such as 0, O, 1, l, I...
	 * Default: narrow
	 *
	 * @return string
	 */
	static public function generate_token($length=8, $possible='narrow')
	{
		if (isset(self::$password_characters[$possible]))
		{
			$possible = self::$password_characters[$possible];
		}

		$password = '';

		$possible_length = strlen($possible) - 1;

		#
		# add random characters to $password for $length
		#

		while ($length--)
		{
			#
			# pick a random character from the possible ones
			#

			$except = substr($password, -$possible_length / 2);

			for ($n = 0 ; $n < 5 ; $n++)
			{
				$char = $possible{mt_rand(0, $possible_length)};

				#
				# we don't want this character if it's already in the password
				# unless it's far enough (half of our possible length)
				#

				if (strpos($except, $char) === false)
				{
					break;
				}
			}

			$password .= $char;
		}

		return $password;
	}
}