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

const TOKEN_NUMERIC = "23456789";
const TOKEN_ALPHA = "abcdefghjkmnpqrstuvwxyz";
const TOKEN_ALPHA_UPCASE = "ABCDEFGHJKLMNPQRTUVWXYZ";
const TOKEN_SYMBOL = "!$=@#";
const TOKEN_SYMBOL_WIDE = '%&()*+,-./:;<>?@[]^_`{|}~';

define('ICanBoogie\TOKEN_NARROW', TOKEN_NUMERIC . TOKEN_ALPHA . TOKEN_SYMBOL);
define('ICanBoogie\TOKEN_MEDIUM', TOKEN_NUMERIC . TOKEN_ALPHA . TOKEN_SYMBOL . TOKEN_ALPHA_UPCASE);
define('ICanBoogie\TOKEN_WIDE', TOKEN_NUMERIC . TOKEN_ALPHA . TOKEN_SYMBOL . TOKEN_ALPHA_UPCASE . TOKEN_SYMBOL_WIDE);

/**
 * Generate a password.
 *
 * @param int $length The length of the password. Default: 8
 * @param string $possible The characters that can be used to create the password.
 * If you defined your own, pay attention to ambiguous characters such as 0, O, 1, l, I...
 * Default: {@link TOKEN_NARROW}
 *
 * @return string
 */
function generate_token($length=8, $possible=TOKEN_NARROW)
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
 * @method string generate_token() generate_token($length=8, $possible=TOKEN_WIDE)
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

	static private function generate_token($length=8, $possible=TOKEN_NARROW)
	{
		$possible_length = strlen($possible);

		if ($length > $possible_length)
		{
			str_repeat($possible, ceil($length / $possible_length));
		}

		return substr(str_shuffle($possible), 0, $length);
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

	$str = strip_tags(trim($str), '<' . implode('><', $allowed_tags) . '>');
	$str = preg_replace('#(<p>|<p\s+[^\>]+>)\s*</p>#', '', $str);

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
	$root = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);

	if (strpos($pathname, $root) === 0)
	{
		return substr($pathname, strlen($root));
	}
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