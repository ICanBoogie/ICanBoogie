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
 * Escape HTML special characters.
 *
 * HTML special characters are escaped using the htmlspecialchars() function with the
 * ENT_COMPAT flag.
 *
 * @param string $str The string to escape.
 * @param string $charset The charset of the string to escape. Defaults to ICanBoogie\CHARSET
 * (utf-8).
 *
 * @return string
 */
function escape($str, $charset=CHARSET)
{
	return htmlspecialchars($str, ENT_COMPAT, $charset);
}

/**
 * Escape all applicable characters to HTML entities.
 *
 * Applicable characters are escaped using the htmlentities() function with the ENT_COMPAT flag.
 *
 * @param string $str The string to escape.
 * @param string $charset The charset of the string to escape. Defaults to ICanBoogie\CHARSET
 * (utf-8).
 *
 * @return string
 */
function escape_all($str, $charset=CHARSET)
{
	return htmlentities($str, ENT_COMPAT, $charset);
}

function capitalize($str)
{
	return mb_convert_case($str, MB_CASE_TITLE);
}

function downcase($str)
{
	return mb_strtolower($str);
}

function upcase($str)
{
	return mb_strtoupper($str);
}

/**
 * Shortens a string at a specified position.
 *
 * @param string $str The string to shorten.
 * @param int $length The desired length of the string.
 * @param float $position Position at which characters can be removed.
 * @param bool $shortened `true` if the string was shortened, `false` otherwise.
 *
 * @return string
 */
function shorten($str, $length=32, $position=.75, &$shortened=null)
{
	$l = mb_strlen($str);

	if ($l <= $length)
	{
		return $str;
	}

	$length--;
	$position = (int) ($position * $length);

	if ($position == 0)
	{
		$str = '…' . mb_substr($str, $l - $length);
	}
	else if ($position == $length)
	{
		$str = mb_substr($str, 0, $length) . '…';
	}
	else
	{
		$str = mb_substr($str, 0, $position) . '…' . mb_substr($str, $l - ($length - $position));
	}

	$shortened = true;

	return $str;
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
 * Sorts an array using a stable sorting algorithm while preserving its keys.
 *
 * A stable sorting algorithm maintains the relative order of values with equal keys.
 *
 * The array is always sorted in ascending order but one can use the array_reverse() function to
 * reverse the array. Also keys are preserved, even numeric ones, use the array_values() function
 * to create an array with an ascending index.
 *
 * @param array &$array
 * @param callable $picker
 */
function stable_sort(&$array, $picker=null)
{
	static $transform, $restore;

	$i = 0;

	if (!$transform)
	{
		$transform = function(&$v, $k) use (&$i)
		{
			$v = array($v, ++$i, $k, $v);
		};

		$restore = function(&$v, $k)
		{
			$v = $v[3];
		};
	}

	if ($picker)
	{
		array_walk
		(
			$array, function(&$v, $k) use (&$i, $picker)
			{
				$v = array($picker($v), ++$i, $k, $v);
			}
		);
	}
	else
	{
		array_walk($array, $transform);
	}

	asort($array);

	array_walk($array, $restore);
}

/**
 * Inserts a value in a array before, or after, at given key.
 *
 * Numeric keys are not preserved.
 *
 * @param $array
 * @param $relative
 * @param $value
 * @param $key
 * @param $after
 */
function array_insert($array, $relative, $value, $key=null, $after=false)
{
	$keys = array_keys($array);
	$pos = array_search($relative, $keys, true);

	if ($after)
	{
		$pos++;
	}

	$spliced = array_splice($array, $pos);

	if ($key !== null)
	{
		$array = array_merge($array, array($key => $value));
	}
	else
	{
		array_unshift($spliced, $value);
	}

	return array_merge($array, $spliced);
}

/**
 * Merge arrays recursively with a different algorithm than PHP.
 *
 * @param array $array1
 * @param array $array2 ...
 *
 * @return array
 */
function array_merge_recursive(array $array1, array $array2=array())
{
	$arrays = func_get_args();

	$merge = array_shift($arrays);

	foreach ($arrays as $array)
	{
		foreach ($array as $key => $val)
		{
			#
			# if the value is an array and the key already exists
			# we have to make a recursion
			#

			if (is_array($val) && array_key_exists($key, $merge))
			{
				$val = array_merge_recursive((array) $merge[$key], $val);
			}

			#
			# if the key is numeric, the value is pushed. Otherwise, it replaces
			# the value of the _merge_ array.
			#

			if (is_numeric($key))
			{
				$merge[] = $val;
			}
			else
			{
				$merge[$key] = $val;
			}
		}
	}

	return $merge;
}

function exact_array_merge_recursive(array $array1, array $array2=array())
{
	$arrays = func_get_args();

	$merge = array_shift($arrays);

	foreach ($arrays as $array)
	{
		foreach ($array as $key => $val)
		{
			#
			# if the value is an array and the key already exists
			# we have to make a recursion
			#

			if (is_array($val) && array_key_exists($key, $merge))
			{
				$val = exact_array_merge_recursive((array) $merge[$key], $val);
			}

			$merge[$key] = $val;
		}
	}

	return $merge;
}


/**
 * Returns information about a variable.
 *
 * The function uses xdebug_var_dump() if [Xdebug](http://xdebug.org/) is installed, otherwise it
 * uses print_r() output wrapped in a PRE element.
 *
 * @param mixed $value
 *
 * @return string
 */
function dump($value)
{
	if (function_exists('xdebug_var_dump'))
	{
		ob_start();

		xdebug_var_dump($value);

		$value = ob_get_clean();
	}
	else
	{
		$value = '<pre>' . escape(print_r($value, true)) . '</pre>';
	}

	return $value;
}

/**
 * Formats the given string by replacing placeholders with the values provided.
 *
 * @param string $str The string to format.
 * @param array $args An array of replacement for the placeholders. Occurences in $str of any
 * key in $args are replaced with the corresponding sanitized value. The sanitization function
 * depends on the first character of the key:
 *
 * * :key: Replace as is. Use this for text that has already been sanitized.
 * * !key: Sanitize using the `ICanBoogie\escape()` function.
 * * %key: Sanitize using the `ICanBoogie\escape()` function and wrap inside a "EM" markup.
 *
 * Numeric indexes can also be used e.g '\2' or "{2}" are replaced by the value of the index
 * "2".
 *
 * @return string
 */
function format($str, array $args=array())
{
	if (!$args)
	{
		return $str;
	}

	$holders = array();
	$i = 0;

	foreach ($args as $key => $value)
	{
		++$i;

		if (is_array($value) || is_object($value))
		{
			$value = dump($value);
		}
		else if (is_bool($value))
		{
			$value = $value ? '<em>true</em>' : '<em>false</em>';
		}
		else if (is_null($value))
		{
			$value = '<em>null</em>';
		}

		if (is_string($key))
		{
			switch ($key{0})
			{
				case ':': break;
				case '!': $value = escape($value); break;
				case '%': $value = '<q>' . escape($value) . '</q>'; break;

				default:
				{
					$escaped_value = escape($value);

					$holders['!' . $key] = $escaped_value;
					$holders['%' . $key] = '<q>' . $escaped_value . '</q>';

					$key = ':' . $key;
				}
			}
		}
		else if (is_numeric($key))
		{
			$key = '\\' . $i;
			$holders['{' . $i . '}'] = $value;
		}

		$holders[$key] = $value;
	}

	return strtr($str, $holders);
}

function log_info($message, array $params=array(), $message_id=null)
{
	Debug::log('info', $message, $params, $message_id);
}