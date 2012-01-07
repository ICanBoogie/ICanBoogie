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
			$value = wd_dump($value);
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
				case '!': $value = wd_entities($value); break;
				case '%': $value = '<q>' . wd_entities($value) . '</q>'; break;

				default:
				{
					$escaped_value = wd_entities($value);

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