<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (function_exists('mb_internal_encoding'))
{
	mb_internal_encoding(ICanBoogie\CHARSET);
}

function wd_create_cloud($tags, $callback)
{
	if (empty($tags))
	{
		return;
	}

	$min = min(array_values($tags));
	$max = max(array_values($tags));

	$mid = ($max == $min) ? 1 : $max - $min;

	$rc = '';

	foreach ($tags as $tag => $value)
	{
		$rc .= call_user_func($callback, $tag, $value, ($value - $min) / $mid);
	}

	return $rc;
}

function wd_unaccent_compare($a, $b)
{
    return strcmp(\ICanBoogie\remove_accents($a), \ICanBoogie\remove_accents($b));
}

function wd_unaccent_compare_ci($a, $b)
{
    return strcmp(strtolower(\ICanBoogie\remove_accents($a)), strtolower(\ICanBoogie\remove_accents($b)));
}

function wd_normalize($str, $separator='-', $charset=ICanBoogie\CHARSET)
{
	$str = str_replace('\'', '', $str);
	$str = \ICanBoogie\remove_accents($str, $charset);
	$str = strtolower($str);
	$str = preg_replace('#[^a-z0-9]+#', $separator, $str);
	$str = trim($str, $separator);

	return $str;
}

function wd_discard_substr_by_length($string, $len=3, $separator='-')
{
	if (!$len)
	{
		return $string;
	}

	$ar = explode($separator, $string);
	$ar = array_map('trim', $ar);

	foreach ($ar as $i => $value)
	{
		if (is_numeric($value))
		{
			continue;
		}

		if (strlen($value) < $len)
		{
			unset($ar[$i]);
		}
	}

	$string = implode($separator, $ar);

	return $string;
}

function wd_strip_slashes_recursive($value)
{
	return is_array($value) ? array_map(__FUNCTION__, $value) : stripslashes($value);
}

function wd_kill_magic_quotes()
{
	if (get_magic_quotes_gpc())
	{
		$_GET = array_map('wd_strip_slashes_recursive', $_GET);
		$_POST = array_map('wd_strip_slashes_recursive', $_POST);
		$_COOKIE = array_map('wd_strip_slashes_recursive', $_COOKIE);
		$_REQUEST = array_map('wd_strip_slashes_recursive', $_REQUEST);
	}
}

/**
 * Sort an array of arrays using a member of the arrays.
 *
 * Unlike the new sort, the order of the array with the same value are preserved.
 *
 * @param $array
 * @param $by
 * @param $callback
 *
 * @return array The array sorted.
 */
function wd_array_sort_by(&$array, $by, $callback='ksort')
{
	$sorted_by = array();

	foreach ($array as $key => $value)
	{
		$sorted_by[$value[$by]][$key] = $value;
	}

	$callback($sorted_by);

	$array = array();

	foreach ($sorted_by as $sorted)
	{
		$array += $sorted;
	}

	return $array;
}

function wd_array_sort_and_filter($filter, array $array1)
{
	#
	# `filter` is provided as an array of values, but because we need keys we have to flip it.
	#

	$filter = array_flip($filter);

	#
	# multiple arrays can be provided, they are all merged with the `filter` as first array so that
	# values appear in the order defined in `filter`.
	#

	$arrays = func_get_args();

	array_shift($arrays);
	array_unshift($arrays, $filter);

	$merged = call_user_func_array('array_merge', $arrays);

	#
	# Now we can filter the array using the keys defined in `filter`.
	#

	return array_intersect_key($merged, $filter);
}

function wd_array_to_xml($array, $parent='root', $encoding='utf-8', $nest=1)
{
	$rc = '';

	if ($nest == 1)
	{
		#
		# first level, time to write the XML header and open the root markup
		#

		$rc .= '<?xml version="1.0" encoding="' . $encoding . '"?>' . PHP_EOL;
		$rc .= '<' . $parent . '>' . PHP_EOL;
	}

	$tab = str_repeat("\t", $nest);

	if (substr($parent, -3, 3) == 'ies')
	{
		$collection = substr($parent, 0, -3) . 'y';
	}
	else if (substr($parent, -2, 2) == 'es')
	{
		$collection = substr($parent, 0, -2);
	}
	else if (substr($parent, -1, 1) == 's')
	{
		$collection = substr($parent, 0, -1);
	}
	else
	{
		$collection = 'entry';
	}

	foreach ($array as $key => $value)
	{
		if (is_numeric($key))
		{
			$key = $collection;
		}

		if (is_array($value) || is_object($value))
		{
			$rc .= $tab . '<' . $key . '>' . PHP_EOL;
			$rc .= wd_array_to_xml((array) $value, $key, $encoding, $nest + 1);
			$rc .= $tab . '</' . $key . '>' . PHP_EOL;

			continue;
		}

		#
		# if we find special chars, we put the value into a CDATA section
		#

		if (strpos($value, '<') !== false || strpos($value, '>') !== false || strpos($value, '&') !== false)
		{
			$value = '<![CDATA[' . $value . ']]>';
		}

		$rc .= $tab . '<' . $key . '>' . $value . '</' . $key . '>' . PHP_EOL;
	}

	if ($nest == 1)
	{
		#
		# first level, time to close the root markup
		#

		$rc .= '</' . $parent . '>';
	}

	return $rc;
}

function wd_excerpt($str, $limit=55)
{
	$allowed_tags = array
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

//			var_dump($words);

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


function wd_camelize($str, $separator='-')
{
	static $callback;

	if (!$callback)
	{
		$callback = create_function('$match', 'return mb_strtoupper(mb_substr($match[0], 1));');
	}

	return preg_replace_callback('/' . preg_quote($separator) . '\D/', $callback, $str);
}

function wd_hyphenate($str)
{
	static $callback;

	if (!$callback)
	{
		$callback = create_function('$match', 'return "-" . mb_strtolower(mb_substr($match[0], 0, 1));');
	}

	return trim(preg_replace_callback('/[A-Z]/', $callback, $str), '-');
}

function wd_strip_root($str)
{
	return substr($str, strlen($_SERVER['DOCUMENT_ROOT']));
}

use ICanBoogie\Debug;

function wd_log($str, array $params=array(), $message_id=null, $type='debug')
{
	Debug::log($type, $str, $params, $message_id);
}

function wd_log_done($str, array $params=array(), $message_id=null)
{
	Debug::log('success', $str, $params, $message_id);
}

function wd_log_error($str, array $params=array(), $message_id=null)
{
	Debug::log('error', $str, $params, $message_id);
}

function wd_log_time($str, array $params=array())
{
	static $reference;
	static $last;

	if (!$reference)
	{
		global $wddebug_time_reference;

		$reference = isset($wddebug_time_reference) ? $wddebug_time_reference : microtime(true);

		// TODO-20100525: the first call is used as an initializer, we have to find a better way
		// to initialize the reference time.

		//		return;
	}

	$now = microtime(true);

	$add = '<var>[';

	$add .= '∑' . number_format($now - $reference, 3, '\'', '') . '"';

	if ($last)
	{
		$add .= ', +' . number_format($now - $last, 3, '\'', '') . '"';
	}

	$add .= ']</var>';

	$last = $now;

	$str = $add . ' ' . $str;

	wd_log($str, $params);
}