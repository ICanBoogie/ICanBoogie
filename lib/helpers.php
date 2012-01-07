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

	if (!$transform)
	{
		$transform = function(&$v, $k)
		{
			$v = array($v, $k, $v);
		};

		$restore = function(&$v, $k)
		{
			$v = $v[2];
		};
	}

	if ($picker)
	{
		array_walk
		(
			$array, function(&$v, $k) use ($picker)
			{
				$v = array($picker($v), $k, $v);
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
		else if (is_string($key))
		{
			switch ($key{0})
			{
				case ':': break;
				case '!': $value = escape($value); break;
				case '%': $value = '<q>' . escape($value) . '</q>'; break;

				default:
				{
					$escaped_value = escape($value);

					$holders["!$key"] = $escaped_value;
					$holders["%$key"] = '<q>' . $escaped_value . '</q>';

					$key = ":$key";
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