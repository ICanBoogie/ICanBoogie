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
	/**
	 * Singleton instance of the class.
	 *
	 * @var Events
	 */
	static protected $instance;

	/**
	 * Callback to initialize events.
	 *
	 * @var callable
	 */
	static public $initializer;

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return Events
	 */
	static public function get()
	{
		if (!self::$instance)
		{
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Synthesizes events config.
	 *
	 * Events are retrieved from the "hooks" config, under the "events" namespace.
	 *
	 * @param array $fragments
	 * @throws \InvalidArgumentException when a callback is not properly defined.
	 *
	 * @return array[string]array
	 */
	static public function synthesize_config(array $fragments)
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

				#
				# because modules are ordered by weight (most important are first), we can
				# push callbacks instead of unshifting them.
				#

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
	}

	/**
	 * Event collection.
	 *
	 * @var array[string]array
	 */
	protected $events = array();

	/**
	 * Calls the event initializer if it is defined.
	 *
	 * @see Events::$initializer
	 */
	protected function __construct()
	{
		if (self::$initializer)
		{
			$this->events = call_user_func(self::$initializer, $this);
		}
	}

	/**
	 * Returns an iterator for event callbacks.
	 *
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->events);
	}

	/**
	 * Checks if a callback exists for a event.
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($offset)
	{
		return isset($this->events[$offset]);
	}

	/**
	 * Returns the callbacks for a event.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($offset)
	{
		return $this->offsetExists($offset) ? $this->events[$offset] : array();
	}

	/**
	 * @throws OffsetNotWritable in attempt to set an offset.
	 */
	public function offsetSet($offset, $value)
	{
		throw new OffsetNotWritable(array($offset, $this));
	}

	/**
	 * @throws OffsetNotWritable in attempt to unset an offset.
	 */
	public function offsetUnset($offset)
	{
		throw new OffsetNotWritable(array($offset, $this));
	}

	/**
	 * Lists of skippable event types.
	 *
	 * @var array[string]bool
	 */
	protected $skippable = array();

	/**
	 * Marks an event type as skippable.
	 *
	 * @param string $type
	 */
	public function skip($type)
	{
		$this->skippable[$type] = true;
	}

	/**
	 * Returns whether or not an event has been marked as skippable.
	 *
	 * @param string $type
	 *
	 * @return boolean `true` if the event can be skipped, `false` otherwise.
	 */
	public function is_skippable($type)
	{
		return isset($this->skippable[$type]);
	}

	/**
	 * Attaches a hook to an event type.
	 *
	 * @param string $type
	 * @param callable $callback
	 *
	 * @throws \InvalidArgumentException when $callback is not a callable.
	 */
	static public function attach($type, $callback)
	{
		if (!is_callable($callback))
		{
			throw new \InvalidArgumentException(format
			(
				'Event callback must be a callable, %type given: :callback', array
				(
					'type' => gettype($callback),
					'callback' => $callback
				)
			));
		}

		$events = static::get();
		$events->skippable = array();
		$ns = '::';

		if (strpos($type, '::'))
		{
			list($ns, $type) = explode('::', $type);

			$events->events_by_class = array();
		}

		if (!isset($events->events[$ns][$type]))
		{
			$events->events[$ns][$type] = array();
		}

		array_unshift($events->events[$ns][$type], $callback);
	}

	/**
	 * Detaches a event callback from an event type.
	 *
	 * @param string $type The type of the event.
	 * @param callable $callback The event callback.
	 *
	 * @return void
	 *
	 * @throws Exception when the event callback doesn't exists.
	 */
	static public function detach($type, $callback)
	{
		$ns = '::';

		if (strpos($type, '::'))
		{
			list($ns, $type) = explode('::', $type);
		}

		$events = static::get();

		if (isset($events->events[$ns][$type]))
		{
			foreach ($events->events[$ns][$type] as $key => $c)
			{
				if ($c != $callback)
				{
					continue;
				}

				unset($events->events[$ns][$type][$key]);

				return;
			}
		}

		throw new Exception('Unknown event callback: \1', array($callback));
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
 *
 * @property-read $stopped bool The {@link $stopped} property is readable.
 * @property-read $used int The {@link $used} property is readable.
 * @property-read $target mixed The {@link $target} property is readable.
 */
class Event
{
	/**
	 * The reserved properties that cannot be used to provide event properties.
	 *
	 * @var array[string]bool
	 */
	static private $reserved = array('chain' => true, 'stopped' => true, 'target' => true, 'used' => true);

	static public $profiling = array
	(
		'callbacks' => array(),
		'unused' => array()
	);

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
	 * Chain of callbacks to execute once the event has been fired.
	 *
	 * @var array
	 */
	private $chain = array();

	/**
	 * Creates an event and fires it immediately.
	 *
	 * If $target is provided the callbacks are narrowed to classes events and callbacks are
	 * called with $target as second parameter.
	 *
	 * @param mixed $target The target of the event.
	 * @param string $type The event type.
	 * @param array $properties
	 *
	 * @throws PropertyIsReserved in attempt to use a reserved property.
	 */
	protected function __construct($target, $type, array $properties)
	{
		$this->target = $target;

		$events = Events::get();

		#
		# filters events according to the target.
		#

		if ($target)
		{
			$class = get_class($target);
			$complete_type = $class . '::' . $type;
			$filtered_events = $events->get_class_events($class);
		}
		else
		{
			$complete_type = $type;
			$filtered_events = $events['::'];
		}

		if ($events->is_skippable($complete_type))
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
				foreach ($properties as $property => &$value)
				{
					if (isset(self::$reserved[$property]))
					{
						throw new PropertyIsReserved($property);
					}

					#
					# we need to set the property to null before we set its value by reference
					# otherwise if the property doesn't exists the magic method {@link __get()} is
					# invoked and throws an exception because we try to get the value of a
					# property that does not exists.
					#

					$this->$property = null;
					$this->$property = &$value;
				}

				$prepared = true;
			}

			foreach ($callbacks as $callback)
			{
				++$this->used;

				$time = microtime(true);

				call_user_func($callback, $this, $target);

				self::$profiling['callbacks'][] = array($time, $complete_type, $callback, microtime(true) - $time);

				if ($this->stopped)
				{
					return;
				}
			}

			foreach ($this->chain as $callback)
			{
				++$this->used;

				$time = microtime(true);

				call_user_func($callback, $this, $target);

				self::$profiling['callbacks'][] = array($time, $type, $callback, microtime(true) - $time);

				if ($this->stopped)
				{
					return;
				}
			}
		}

		if (!$this->used)
		{
			self::$profiling['unused'][] = array(microtime(true), $complete_type);

			$events->skip($complete_type);
		}
	}

	private function dispatch($target, $type, $complete_type, array $properties, array $filtered_events)
	{

	}

	/**
	 * Returns the read-only properties {@link $stopped}, {@link $used} and {@link $target}.
	 *
	 * @param string $property The read-only property to return.
	 *
	 * @throws PropertyNotReadable if the property exists but is not readable.
	 * @throws PropertyNotFound if the property doesn't exists.
	 *
	 * @return mixed
	 */
	public function __get($property)
	{
		switch ($property)
		{
			case 'stopped': return $this->stopped;
			case 'used': return $this->used;
			case 'target': return $this->target;
		}

		$properties = get_object_vars($this);

		if (array_key_exists($property, $properties))
		{
			throw new PropertyNotReadable(array($property, $this));
		}

		throw new PropertyNotFound(array($property, $this));
	}

	/**
	 * Stops the callbacks chain.
	 *
	 * After the `stop()` method is called the callback chain is broken and no other callback
	 * is called.
	 */
	public function stop()
	{
		$this->stopped = true;
	}

	/**
	 * Add a callback to the finish chain.
	 *
	 * The finish chain is executed once the event chain is done and was not stopped.
	 *
	 * @param callable $callback
	 *
	 * @return \ICanBoogie\Event
	 */
	public function chain($callback)
	{
		$this->chain[] = $callback;

		return $this;
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
	static public function fire($type, array $properties, $target=null)
	{
		$event = new self($target, $type, $properties);

		return $event;
	}
}

/**
 * Raised when property has a name reserved by a class.
 */
class PropertyIsReserved extends \RuntimeException
{
	private $property;

	public function __construct($property, $code=500, \Exception $previous=null)
	{
		parent::__construct("Property <q>$property</q> is reserved.", $code, $previous);
	}

	public function __get($property)
	{
		switch ($property)
		{
			case 'property': return $this->property;
		}
	}
}