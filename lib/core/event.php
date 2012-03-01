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
 * Events collected from the "hooks" config or added by the user.
 */
class Events implements \IteratorAggregate, \ArrayAccess
{
	protected static $instance;

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return \ICanBoogie\Events
	 */
	public static function get()
	{
		if (!self::$instance)
		{
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected $events;

	/**
	 * Constructor.
	 *
	 * Events are gathered from the "hooks" config fragments under the `events` key.
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function __construct()
	{
		global $core;

		$this->events = $core->configs->synthesize
		(
			'events', function($fragments)
			{
				$events = array();

				foreach ($fragments as $path => $fragment)
				{
					if (empty($fragment['events']))
					{
						continue;
					}

					foreach ($fragment['events'] as $type => $callback)
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

						if (strpos($type, '::'))
						{
							list($class, $type) = explode('::', $type);

							$events[$class][$type][] = $callback;
						}
						else
						{
							$events['::'][$type][] = $callback;
						}
					}
				}

				return $events;
			},

			'hooks'
		);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->events);
	}

	public function offsetExists($offset)
	{
		return isset($this->events[$offset]);
	}

	public function offsetGet($offset)
	{
		return $this->offsetExists($offset) ? $this->events[$offset] : array();
	}

	public function offsetSet($offset, $value)
	{
		throw new Exception\OffsetNotWritable(array($offset, $this));
	}

	public function offsetUnset($offset)
	{
		throw new Exception\OffsetNotWritable(array($offset, $this));
	}

	protected $skippable = array();

	/**
	 * Mark an event type as skippable.
	 *
	 * @param string $type
	 */
	public function skip($type)
	{
		$this->skippable[$type] = true;
	}

	/**
	 * Returns wheter or not an event has been marked as skippable.
	 *
	 * @param string $type
	 *
	 * @return boolean `true` if the event can be skipped, `false` otherwise.
	 */
	public function is_skippable($type)
	{
		return !empty($this->skippable[$type]);
	}

	/**
	 * Adds a hook to an event type.
	 *
	 * @param string $pattern
	 * @param callable $callback
	 *
	 * @throws \InvalidArgumentException when $callback is not a callable.
	 */
	public static function add($type, $callback)
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

		$events = static::get();
		$events->skipable = array();

		if (strpos($type, '::'))
		{
			list($class, $type) = explode('::', $type);

			$events->events[$class][$type][] = $callback;
			$events->events_by_class = array();
		}
		else
		{
			$events->events['::'][$type][] = $callback;
		}
	}

	/**
	 * Removes a hook from an event type.
	 *
	 * @param string $type
	 * @param callable $callback
	 */
	public static function remove($type, $callback)
	{
		// TODO
	}

	/**
	 * Returns the event types associated with a class.
	 *
	 * @param string $class
	 *
	 * @return array
	 */
	public function get_class_events($class)
	{
		if (isset($this->events_by_class[$class]))
		{
			return $this->events_by_class[$class];
		}

		$events = array();
		$c = $class;

		while ($c)
		{
			if (isset($this->events[$c]))
			{
				$events = \array_merge_recursive($events, $this->events[$c]);
			}

			$c = get_parent_class($c);
		}

		$this->events_by_class[$class] = $events;

		return $events;
	}

	protected $events_by_class = array();
}

/**
 * An event.
 */
class Event
{
	/**
	 * `true` when the event was stopped.
	 *
	 * @var bool
	 */
	private $stopped = false;

	/**
	 * The number of callbacks called.
	 *
	 * @var int
	 */
	private $used = 0;

	/**
	 * The object target of the event.
	 *
	 * @var mixed
	 */
	private $target;

	/**
	 * The reserved properties that cannot be used to provide event properties.
	 *
	 * @var array[string]bool
	 */
	private static $reserved = array('stopped' => true, 'target' => true, 'used' => true);

	public static $profiling = array();

	/**
	 * Creates an event and fires it immediately.
	 *
	 * If $target is provided the callbacks are narrowed to classes events and callbacks are
	 * called with $target as second parameter.
	 *
	 * @param string $type Event type.
	 * @param array $property Properties of the event.
	 * @param object|null $target The target of the event.
	 *
	 * @return Event|null The event that was created or null if the fire triggered nothing.
	 */
	protected function __construct($target, array $properties, $type)
	{
		$events = Events::get();

		#
		# filters events events according to the target.
		#

		if ($target)
		{
			$class = get_class($target);
			$skippable_type = $class . '::' . $type;
			$filtered_events = $events->get_class_events($class);
		}
		else
		{
			$skippable_type = $type;
			$filtered_events = $events['::'];
		}

		if (!isset(self::$profiling[$skippable_type]))
		{
			self::$profiling[$skippable_type] = array();
		}

		if ($events->is_skippable($skippable_type))
		{
			return;
		}

		$prepared = false;

		foreach ($filtered_events as $pattern => $callbacks)
		{
			if ($pattern != $type)
			{
				continue;
			}

			if (!$prepared)
			{
				$this->target = $target;

				foreach ($properties as $property => &$value)
				{
					if (isset(self::$reserved[$property]))
					{
						throw new Exception\PropertyNotWritable(format('%property is a reserved property.', array('property' => $property)));
					}

					$this->$property = &$value;
				}

				$prepared = true;
			}

			foreach ($callbacks as $callback)
			{
				++$this->used;
				self::$profiling[$skippable_type][] = $callback;

				call_user_func($callback, $this, $target);

				if ($this->stopped)
				{
					return;
				}
			}
		}

		if (!$this->used)
		{
			$events->skip($skippable_type);
		}
	}

	/**
	 * Stops the callbacks chain.
	 *
	 * After the `stop()` method is called the callback chain is brokken and no other callback
	 * is called.
	 */
	public function stop()
	{
		$this->stopped = true;
	}

	// COMPAT

	/**
	 * Fires an event.
	 *
	 * @param object|null $target
	 * @param array $properties
	 * @param string $type
	 *
	 * @return Event The event created, or `null` if there was no callback for the event type.
	 */
	public static function fire($type, array $properties, $target=null)
	{
		$event = new self($target, $properties, $type);

		return $event;
	}
}