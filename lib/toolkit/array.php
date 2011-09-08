<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class WdArray
{
	static public function flatten(array $array)
	{
//		$result = array(); FIXME: not sure about all of this :-)
		$result = $array;

		foreach ($array as $key => &$value)
		{
			self::flatten_callback($result, '', $key, $value);
		}

		return $result;
	}

	static private function flatten_callback(&$result, $pre, $key, &$value)
	{
		if (is_array($value))
		{
			foreach ($value as $vk => &$vv)
			{
				self::flatten_callback($result, $pre ? ($pre . '[' . $key . ']') : $key, $vk, $vv);
			}
		}
		else if (is_object($value))
		{
			// FIXME: throw new Exception('Don\'t know what to do with objects: \1', $value);
		}
		else
		{
			/* FIXME-20100520: this has been desabled because sometime empty values (e.g. '') are
			 * correct values. The function was first used with BrickRouge\Form which made sense at the time
			 * but now changing values would be a rather strange behavious.
			#
			# a trick to create undefined values
			#

			if (!strlen($value))
			{
				$value = null;
			}
			*/

			if ($pre)
			{
				#
				# only arrays are flattened
				#

				$pre .= '[' . $key . ']';

				$result[$pre] = $value;
			}
			else
			{
				#
				# simple values are kept intact
				#

				$result[$key] = $value;
			}
		}
	}

	static public function group_by($array, $key)
	{
		$group = array();

		foreach ($array as $sub)
		{
			$value = is_object($sub) ? $sub->$key : $sub[$key];

			$group[$value][] = $sub;
		}

		return $group;
	}

	static public function reorder_by_property(array $entries, array $order, $property)
	{
		$by_property = array();

		foreach ($entries as $entry)
		{
			$by_property[is_object($entry) ? $entry->$property : $entry[$property]] = $entry;
		}

		$rc = array();

		foreach ($order as $o)
		{
			if (empty($by_property[$o]) || array_key_exists($o, $by_property[$o]))
			{
				continue;
			}

			$rc[] = $by_property[$o];
		}

		return $rc;
	}

	static public function by_columns(array $array, $columns, $pad=false)
	{
		$values_by_columns = ceil(count($array) / $columns);

		$i = 0;
		$by_columns = array();

		foreach ($array as $value)
		{
			$by_columns[$i++ % $values_by_columns][] = $value;
		}

		return $by_columns;
	}

	public static function stable_sort(&$array, $picker=null)
	{
		static $dec, $undec;

		if (!$dec)
		{
			$dec = function(&$v, $k)
			{
				$v = array($v, $k, $v);
			};

			$undec = function(&$v, $k)
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
			array_walk($array, $dec);
		}

		asort($array);

		array_walk($array, $undec);
	}
}

/*
function wd_array_by_columns(array $array, $columns, $pad=false)
{
	$values_by_columns = ceil(count($array) / $columns);

	$i = 0;
	$by_columns = array();

	foreach ($array as $value)
	{
		$by_columns[$i++ % $values_by_columns][] = $value;
	}

	$finish = array();

	foreach ($by_columns as $column)
	{
		if ($pad)
		{
			$column = array_pad($column, $columns, null);
		}

		foreach ($column as $value)
		{
			$finish[] = $value;
		}
	}

	return $finish;
}
*/

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

function wd_array_insert($array, $relative, $value, $key=null, $after=false)
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

function wd_stable_sort(&$array, $picker=null)
{
	static $dec, $undec;

	if (!$dec)
	{
		$dec = function(&$v, $k)
		{
			$v = array($v, $k, $v);
		};

		$undec = function(&$v, $k)
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
		array_walk($array, $dec);
	}

	asort($array);

	array_walk($array, $undec);
}