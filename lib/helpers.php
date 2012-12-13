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
 * Generate a password.
 *
 * @param int $length The length of the password. Default: 8
 * @param string $possible The characters that can be used to create the password.
 * If you defined your own, pay attention to ambiguous characters such as 0, O, 1, l, I...
 * Default: narrow
 *
 * @return string
 */
function generate_token($length=8, $possible='narrow')
{
	return Helpers::generate_token($length, $possible);
}

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
function pbkdf2($p, $s, $c=1000, $kl=32, $a='sha256')
{
	return Helpers::pbkdf2($p, $s, $c=1000, $kl=32, $a='sha256');
}

/**
 * Patchable helpers of the ICanBoogie package.
 *
 * @method string generate_token() generate_token($length=8, $possible='narrow')
 * @method string pbkdf2() pbkdf2($p, $s, $c=1000, $kl=32, $a='sha256')
 */
class Helpers
{
	static private $jumptable = array
	(
		'generate_token' => array(__CLASS__, 'generate_token'),
		'pbkdf2' => array(__CLASS__, 'pbkdf2')
	);

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
	 * @param collable $callback Callback.
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

	static public $password_characters = array
	(
		'narrow' => '$=@#23456789bcdfghjkmnpqrstvwxyz',
		'medium' => '$=@#23456789bcdfghjkmnpqrstvwxyzBCDFGHJKMNPQRSTVWXYZ',
		'wide' => '!"#$%&()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~'
	);

	static private function generate_token($length=8, $possible='narrow')
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

/**
 * Registers a simple autoloader for ICanBoogie classes.
 */
function register_autoloader()
{
	spl_autoload_register
	(
		function($name)
		{
			static $index;

			if ($index === null)
			{
				$path = ROOT; // the $path variable is used within the autoload file
				$index = require $path . 'config/autoload.php';
			}

			if (isset($index[$name]))
			{
				require_once $index[$name];
			}
		}
	);
}

/**
 * Normalize a string to be suitable as a namespace part.
 *
 * @param string $part The string to normalize.
 *
 * @return string Normalized string.
 */
function normalize_namespace_part($part)
{
	return preg_replace_callback
	(
		'/[-\s_\.]\D/', function ($match)
		{
			$rc = ucfirst($match[0]{1});

			if ($match[0]{0} == '.')
			{
				$rc = '\\' . $rc;
			}

			return $rc;
		},

		' ' . $part
	);
}

// https://github.com/rails/rails/blob/master/activesupport/lib/active_support/inflector/inflections.rb
// http://api.rubyonrails.org/classes/ActiveSupport/Inflector.html#method-i-singularize

function singularize($string)
{
	static $rules = array
	(
		'/ies$/' => 'y',
		'/s$/' => ''
	);

	return preg_replace(array_keys($rules), $rules, $string);
}

/**
 * Creates an excerpt of an HTML string.
 *
 * The following tags are preserved: A, P, CODE, DEL, EM, INS and STRONG.
 *
 * @param string $str HTML string.
 * @param int $limit The maximum number of words.
 *
 * @return string
 */
function excerpt($str, $limit=55)
{
	static $allowed_tags = array
	(
		'a', 'p', 'code', 'del', 'em', 'ins', 'strong'
	);

	$str = strip_tags((string) $str, '<' . implode('><', $allowed_tags) . '>');

	$parts = preg_split('#<([^\s>]+)([^>]*)>#m', $str, 0, PREG_SPLIT_DELIM_CAPTURE);

	# i+0: text
	# i+1: markup ('/' prefix for closing markups)
	# i+2: markup attributes

	$rc = '';
	$opened = array();

	foreach ($parts as $i => $part)
	{
		if ($i % 3 == 0)
		{
			$words = preg_split('#(\s+)#', $part, 0, PREG_SPLIT_DELIM_CAPTURE);

			foreach ($words as $w => $word)
			{
				if ($w % 2 == 0)
				{
					if (!$word) // TODO-20100908: strip punctuation
					{
						continue;
					}

					$rc .= $word;

					if (!--$limit)
					{
						break;
					}
				}
				else
				{
					$rc .= $word;
				}
			}

			if (!$limit)
			{
				break;
			}
		}
		else if ($i % 3 == 1)
		{
			if ($part[0] == '/')
			{
				$rc .= '<' . $part . '>';

				array_shift($opened);
			}
			else
			{
				array_unshift($opened, $part);

				$rc .= '<' . $part . $parts[$i + 1] . '>';
			}
		}
	}

	if (!$limit)
	{
		$rc .= ' <span class="excerpt-warp">[…]</span>';
	}

	if ($opened)
	{
		$rc .= '</' . implode('></', $opened) . '>';
	}

	return $rc;
}

/**
 * Removes the `DOCUMENT_ROOT` from the provided path.
 *
 * @param string $pathname
 *
 * @return string
 */
function strip_root($pathname)
{
	return substr($pathname, strlen($_SERVER['DOCUMENT_ROOT']));
}

/**
 * Logs a message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 * @param string $message_id Message identifier.
 * @param string $type Message type, one of "success", "error", "info" and "debug". Defaults to
 * "debug".
 */
function log($message, array $params=array(), $message_id=null, $type='debug')
{
	Debug::log($type, $message, $params, $message_id);
}

/**
 * Logs a success message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 * @param string $message_id Message identifier.
 */
function log_success($message, array $params=array(), $message_id=null)
{
	Debug::log('success', $message, $params, $message_id);
}

/**
 * Logs an error message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 * @param string $message_id Message identifier.
 */
function log_error($message, array $params=array(), $message_id=null)
{
	Debug::log('error', $message, $params, $message_id);
}

/**
 * Logs an info message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 * @param string $message_id Message identifier.
 */
function log_info($message, array $params=array(), $message_id=null)
{
	Debug::log('info', $message, $params, $message_id);
}

/**
 * Logs a debug message associated with a timing information.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_time($message, array $params=array())
{
	static $last;

	$now = microtime(true);

	$add = '<var>[';

	$add .= '∑' . number_format($now - $_SERVER['REQUEST_TIME_FLOAT'], 3, '\'', '') . '"';

	if ($last)
	{
		$add .= ', +' . number_format($now - $last, 3, '\'', '') . '"';
	}

	$add .= ']</var>';

	$last = $now;

	$message = $add . ' ' . $message;

	log($message, $params);
}