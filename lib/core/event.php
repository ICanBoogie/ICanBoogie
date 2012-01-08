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

	public static function translate_regex($pattern)
	{
		if (strpos($pattern, '*') !== false || strpos($pattern, '?') !== false)
		{
			$pattern = self::DELIMITER . '^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern, self::DELIMITER)) . '$' . self::DELIMITER;
		}

		return $pattern;
	}

	protected static $listeners = array();

	/**
	 * Returns listeners.
	 *
	 * When the function is called for the first time, listeners are built from the 'hooks' config
	 * buy filtering definitions under 'events'.
	 *
	 * @return array[string]array
	 */
	protected static function get_listeners()
	{
		global $core;

		if (empty(self::$listeners))
		{
			self::$listeners = $core->configs->synthesize
			(
				'events', function($fragments)
				{
					global $core;

					$listeners = array();

					foreach ($fragments as $path => $fragment)
					{
						if (empty($fragment['events']))
						{
							continue;
						}

						foreach ($fragment['events'] as $pattern => $callback)
						{
							if (!is_string($callback))
							{
								throw new \InvalidArgumentException(format
								(
									'Event callback must be a string, %type given: :callback in %path', array
									(
										'type' => gettype($callback),
										'callback' => $callback,
										'path' => $path . 'config/hooks.php'
									)
								));
							}

							if (strpos($pattern, '::'))
							{
								list($class, $pattern) = explode('::', $pattern);

								$listeners['__by_class'][$class][$pattern][] = $callback;
							}
							else
							{
								$listeners['__by_type'][Event::translate_regex($pattern)][] = $callback;
							}
						}
					}

					return $listeners;
				},

				'hooks'
			);
		}

		return self::$listeners;
	}

	/**
	 * Adds event callback.
	 *
	 * @param string $pattern
	 * @param callable $callback
	 *
	 * @throws \InvalidArgumentException when $callback is not a callable.
	 */
	public static function add($pattern, $callback)
	{
		if (!is_callable($callback))
		{
			throw new \InvalidArgumentException(format
			(
				'Event callback must be a callable, %type given: :callback in %path', array
				(
					'type' => gettype($callback),
					'callback' => $callback,
					'path' => $path . 'config/hooks.php'
				)
			));
		}

		self::$skipable = array();

		if (strpos($pattern, '::'))
		{
			list($class, $pattern) = explode('::', $pattern);

			self::$listeners['__by_class'][$class][$pattern][] = $callback;
		}
		else
		{
			self::$listeners['__by_type'][self::translate_regex($pattern)][] = $callback;
		}
	}

	public static function remove($event, $callback) // FIXME-20120801: I don't think this is working
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

	static private $listeners_by_class = array();

	/**
	 * Returns the callbacks associated with a class.
	 *
	 * @param string $class
	 *
	 * @return array[string]string
	 */
	static private function get_class_listeners($class)
	{
		if (isset(self::$listeners_by_class[$class]))
		{
			return $listeners_by_class[$class];
		}

		$listeners = self::get_listeners();

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

	private static $skipable = array();

	/**
	 * Fires an event
	 *
	 * If $sender is provided the callbacks are narrowed to classes events and $sender is available
	 * as a third parameter.
	 *
	 * @param string $type Event type.
	 * @param array $params Parameters of the event, they are copied as reference into the Event
	 * object.
	 * @param object|null $sender The object sending the event.
	 *
	 * @return Event|null The event that was created or null if the fire triggered nothing.
	 */
	public static function fire($type, array $params=array(), $sender=null)
	{
		$event = null;

		if ($sender)
		{
			$class = get_class($sender);

			if (isset(self::$skipable[$class . '::' . $type]))
			{
				return;
			}

			$listeners = self::get_class_listeners($class);

			foreach ($listeners as $pattern => $callbacks)
			{
				if ($type != $pattern)
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
					call_user_func($callback, $event, $sender);

					if ($event->_stop)
					{
						return $event;
					}
				}
			}

			if (!$event)
			{
				self::$skipable[$class . '::' . $type] = true;
			}

			return $event;
		}

		#
		# by types
		#

		if (isset(self::$skipable[$type]))
		{
			return;
		}

		$listeners = self::get_listeners();
		$patterns = $listeners['__by_type'];

		foreach ($patterns as $pattern => $callbacks)
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
				call_user_func($callback, $event);

				if ($event->_stop)
				{
					return $event;
				}
			}
		}

		if (!$event)
		{
			self::$skipable[$type] = true;
		}

		return $event;
	}

	/**
	 * Wraps a callback between a '<type>:before' and '<type>' event.
	 *
	 * @param callable $callback
	 * @param string $type Even type.
	 * @param array $params Even parameters.
	 * @param object|null $sender The sender of the event.
	 *
	 * @return mixed The result of the callback.
	 */
	public static function wrap($callback, $type, array $params=array(), $sender=null)
	{
		self::fire($type . ':before', $params, $sender);
		$rc = $callback($params, $sender);
		self::fire($type, $params, $sender);

		return $rc;
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