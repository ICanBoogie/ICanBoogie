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

class Event
{
	const DELIMITER = '~';

	private static function translate_regex($pattern)
	{
		if (strpos($pattern, '*') !== false || strpos($pattern, '?') !== false)
		{
			$pattern = self::DELIMITER . '^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern, self::DELIMITER)) . '$' . self::DELIMITER;
		}

		return $pattern;
	}

	protected static $listeners = array();

	protected static function listeners()
	{
		global $core;

		if (empty(self::$listeners))
		{
			self::$listeners = $core->configs->synthesize('events', array(__CLASS__, 'listeners_construct'), 'hooks');
		}

		return self::$listeners;
	}

	public static function listeners_construct($fragments)
	{
		global $core;

		$listeners = array();

		foreach ($fragments as $path => $fragment)
		{
			if (empty($fragment['events']))
			{
				continue;
			}

			foreach ($fragment['events'] as $pattern => $definition)
			{
				if (!is_array($definition))
				{
					$definition = array($definition);
				}

				$definition += array('weight' => 0);

				if (strpos($pattern, '::'))
				{
					list($class, $pattern) = explode('::', $pattern);

					$listeners['__by_class'][$class][self::translate_regex($pattern)][] = $definition;

					continue;
				}

				$listeners['__by_type'][self::translate_regex($pattern)][] = $definition;
			}
		}

		$picker = function($a) { return $a['weight']; };
		$walker = function(&$v, $k) use ($picker)
		{
			\WdArray::stable_sort($v, $picker);
		};

		if (isset($listeners['__by_type']))
		{
			array_walk($listeners['__by_type'], $walker);
		}

		if (isset($listeners['__by_class']))
		{
			array_walk($listeners['__by_class'], function(&$v, $k) use ($walker) { array_walk($v, $walker); });
		}

		return $listeners;
	}

	public static function add($pattern, $definition)
	{
		if (!is_array($definition))
		{
			$definition = array($definition);
		}

		$definition += array('weight' => 0);

		if (strpos($pattern, '::'))
		{
			list($class, $pattern) = explode('::', $pattern);

			self::$listeners['__by_class'][$class][self::translate_regex($pattern)][] = $definition;

			return;
		}

		self::$listeners['__by_type'][self::translate_regex($pattern)][] = $definition;
	}

	public static function remove($event, $callback)
	{
		if (empty(self::$listeners[$event]))
		{
			return;
		}

		foreach (self::$listeners[$event] as $key => $value)
		{
			if ($value != $callback)
			{
				continue;
			}

			unset(self::$listeners[$event][$key]);

			break;
		}
	}

	static private $listeners_by_class=array();

	static private function get_class_listeners($class)
	{
		if (isset(self::$listeners_by_class[$class]))
		{
			return $listeners_by_class[$class];
		}

		$listeners = self::listeners();

		if (empty($listeners['__by_class']))
		{
			return array();
		}

		$listeners_by_class = $listeners['__by_class'];
		$class_listeners = array();

		while ($class)
		{
			if (isset($listeners_by_class[$class]))
			{
				foreach ($listeners_by_class[$class] as $pattern => $listeners)
				{
					foreach ($listeners as $listener)
					{
						$class_listeners[$pattern][] = $listener;
					}
				}
			}

			$class = get_parent_class($class);
		}

		return $class_listeners;
	}

	public static function fire($type, array $params=array(), $sender=null)
	{
		$event = null;

		if ($sender)
		{
			$listeners = self::get_class_listeners(get_class($sender));

			foreach ($listeners as $pattern => $callbacks)
			{
				if (!($pattern{0} == self::DELIMITER ? preg_match($pattern, $type) : $pattern == $type))
				{
					continue;
				}

				#
				# It's time to call the event callback. If there is no event object created yet, we
				# create one now, otherwise we update its type.
				#

				if (!$event)
				{
					$event = new Event($params);
				}

				foreach ($callbacks as $callback)
				{
					call_user_func($callback[0], $event, $sender);

					if ($event->_stop)
					{
						return $event;
					}
				}
			}

			return $event;
		}



		$listeners = self::listeners();
		$listeners = $listeners['__by_type'];

		foreach ($listeners as $pattern => $definitions)
		{
			if (!($pattern{0} == self::DELIMITER ? preg_match($pattern, $type) : $pattern == $type))
			{
				continue;
			}

			#
			# It's time to call the event callback. If there is no event object created yet, we
			# create one now, otherwise we update its type.
			#

			if (!$event)
			{
				$event = new Event($params);
			}

			foreach ($definitions as $definition)
			{
				list($callback) = $definition;

				if (isset($params['target']) && isset($definition['instanceof']))
				{
					$target = $params['target'];
					$instanceof = $definition['instanceof'];
					$is_instance_of = false;

					if (is_array($instanceof))
					{
						foreach ($instanceof as $name)
						{
							if (!$target instanceof $name)
							{
								continue;
							}

							$is_instance_of = true;

							break;
						}
					}
					else
					{
						$is_instance_of = $target instanceof $instanceof;
					}

					if (!$is_instance_of)
					{
						continue;
					}
				}

				#
				# autoload modules if the callback is prefixed by 'm:'
				#

				if (is_array($callback) && is_string($callback[0]) && $callback[0]{1} == ':' && $callback[0]{0} == 'm')
				{
					global $core;

					$module_id = substr($callback[0], 2);

					if (!isset($core->modules[$module_id]))
					{
						#
						# If the module is unavailable, we silently continue
						#

						continue;
					}

					$callback[0] = $core->modules[$module_id];
				}

				call_user_func($callback, $event);

				if ($event->_stop)
				{
					return $event;
				}
			}
		}

		return $event;
	}

	protected function __construct(array $params=array())
	{
		foreach ($params as $key => &$value)
		{
			$this->$key = &$value;
		}
	}

	private $_stop = false;

	public function stop()
	{
		$this->_stop = true;
	}
}