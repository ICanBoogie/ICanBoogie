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
 * The `Object` class provides advanced getters and setters features.
 *
 * It also provides a method to create instances in the same fashion PDO creates instances with
 * the `FETCH_CLASS` mode, that is the properties of the instance are set *before* its constructor
 * is invoked.
 *
 * Event: property
 * ---------------
 *
 * The `ICanBoogie\Object::property` event is fired when no getter was found in a class to obtain
 * the value of a property.
 *
 * Hooks can be attached to that event to provide the value of the property. Should they be able
 * to provide the value, they must create the `value` property within the event object. Thus, even
 * `null` is considered a valid result.
 */
class Object
{
	/**
	 * Creates a new instance of the class using the supplied properties.
	 *
	 * The instance is created in the same fashion [PDO](http://www.php.net/manual/en/book.pdo.php)
	 * creates instances when fetching objects with the `FETCH_CLASS` mode, that is the properties
	 * of the instance are set *before* its constructor is invoked.
	 *
	 * Note: Because the method uses the [`unserialize`](http://www.php.net/manual/en/function.unserialize.php)
	 * function to create the instance, the `__wakeup()` magic method will be called if it is
	 * defined by the class, and it will be called *before* the constructor.
	 *
	 * @param array $properties Properties to be set before the constructor is invoked.
	 * @param array $construct_args Arguments passed to the constructor.
	 * @param string|null $class_name The name of the instance class. If empty the name of the
	 * called class is used.
	 *
	 * @return Object The new instance.
	 */
	public static function from($properties=null, array $construct_args=array(), $class_name=null)
	{
		if (!$class_name)
		{
			$class_name = get_called_class();
		}

		$properties_count = 0;
		$serialized = '';

		if ($properties)
		{
			$class_reflection = new \ReflectionClass($class_name);
			$class_properties = $class_reflection->getProperties();
			$defaults = $class_reflection->getDefaultProperties();

			$done = array();

			foreach ($class_properties as $property)
			{
				if ($property->isStatic())
				{
					continue;
				}

				$properties_count++;

				$identifier = $property->name;
				$done[] = $identifier;
				$value = null;

				if (array_key_exists($identifier, $properties))
				{
					$value = $properties[$identifier];
				}
				else if (isset($defaults[$identifier]))
				{
					$value = $defaults[$identifier];
				}

				if ($property->isProtected())
				{
					$identifier = "\x00*\x00" . $identifier;
				}
				else if ($property->isPrivate())
				{
					$identifier = "\x00" . $property->class . "\x00" . $identifier;
				}

				$serialized .= serialize($identifier) . serialize($value);
			}

			$extra = array_diff(array_keys($properties), $done);

			foreach ($extra as $name)
			{
				$properties_count++;

				$serialized .= serialize($name) . serialize($properties[$name]);
			}
		}

		$serialized = 'O:' . strlen($class_name) . ':"' . $class_name . '":' . $properties_count . ':{' . $serialized . '}';

		$instance = unserialize($serialized);

		if (method_exists($instance, '__construct'))
		{
			call_user_func_array(array($instance, '__construct'), $construct_args);
		}

		return $instance;
	}

	private static $methods;
	private static $class_methods;

	/**
	 * Synthesizes the methods to inject in classes from the "hooks" config.
	 *
	 * @param array $fragments
	 *
	 * @throws Exception if a method definition doesn't have the 'instanceof' key defined.
	 */
	public static function synthesize_methods(array $fragments)
	{
		$methods = array();

		foreach ($fragments as $root => $fragment)
		{
			if (empty($fragment['objects.methods']))
			{
				continue;
			}

			foreach ($fragment['objects.methods'] as $method => $callback)
			{
				if (strpos($method, '::') === false)
				{
					throw new \InvalidArgumentException(format
					(
						'Invalid method name %method, must be <code>class_name::method_name</code> in %root', array
						(
							'method' => $method,
							'root' => $root . 'config/hooks.php'
						)
					));
				}

				list($class, $method) = explode('::', $method);

				$methods[$class][$method] = $callback;
			}
		}

		return $methods;
	}

	/**
	 * Adds a method to a class.
	 *
	 * @param string $method The name of the method.
	 * @param array $callback Callback function.
	 */
	public static function add_method($method, $callback)
	{
		global $core;

		if (self::$methods === null && isset($core))
		{
			self::$methods = $core->configs['methods'];
		}

		if (strpos($method, '::') === false)
		{
			throw new \InvalidArgumentException(format('Invalid method name %method, must be <code>class_name::method_name</code>', array('method' => $method)));
		}

		list($class, $method) = explode('::', $method);

		self::$class_methods[$class][$method] = $callback;
	}

	/**
	 * Adds methods to classes.
	 *
	 * @param array $definitions
	 */
	public static function add_methods(array $methods)
	{
		foreach ($methods as $method => $callback)
		{
			static::add_method($method, $callback);
		}
	}

	/**
	 * Returns the callbacks associated with the class of the object.
	 *
	 * @return array The callbacks associated with the class of the object.
	 */
	protected static function find_external_methods()
	{
		global $core;

		$class = get_called_class();

		if (isset(self::$class_methods[$class]))
		{
			return self::$class_methods[$class];
		}

		if (self::$methods === null && isset($core))
		{
			self::$methods = $core->configs['methods'];
		}

		$methods = self::$methods;
		$methods_by_class = array();

		$classes = array($class => $class) + class_parents($class);

		foreach ($classes as $c)
		{
			if (empty($methods[$c]))
			{
				continue;
			}

			$methods_by_class += $methods[$c];
		}

		self::$class_methods[$class] = $methods_by_class;

		return $methods_by_class;
	}

	/**
	 * Calls the method callback associated with the nonexistant method called or throws an
	 * exception if none is defined.
	 *
	 * @param string $method
	 * @param array $arguments
	 *
	 * @return mixed The result of the callback.
	 */
	public function __call($method, $arguments)
	{
		$callback = static::find_method_callback($method);

		if (!$callback)
		{
			throw new Exception
			(
				'Unknown method %method for object of class %class.', array
				(
					'%method' => $method,
					'%class' => get_class($this)
				)
			);
		}

		array_unshift($arguments, $this);

		return call_user_func_array($callback, $arguments);
	}

	/**
	 * Returns the value of an innaccessible property.
	 *
	 * Multiple callbacks are tried in order to retrieve the value of the property:
	 *
	 * 1. `__volatile_get_<property>`: Get and return the value of the property.The callback may
	 * not be defined by the object's class, but one can extend the class using the mixin features
	 * of the FObject class.
	 * 2. `__get_<property>`: Get, set and return the value of the property. Because the property
	 * is set, the callback is only called once. The callback may not be defined by the object's
	 * class, but one can extend the class using the mixin features of the FObject class.
	 * 3.Finaly, the `property` event is fired to try and retrieve the value of the
	 * property.
	 *
	 * @param string $property
	 *
	 * @throws Exception\PropertyNotReadable when the property has a protected or private scope and
	 * no suitable callback to retrieve its value.
	 *
	 * @throws Exception\PropertyNotFound when the property is undefined and there is no suitable
	 * callback to retrieve its values.
	 *
	 * @return mixed The value of the innaccessible property. `null` is returned if the property
	 * could not be retrieved, in which case an exception is raised.
	 */
	public function __get($property)
	{
		$getter = '__volatile_get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$getter();
		}

		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$property = $this->$getter();
		}

		#
		# The object does not define any getter for the property, let's see if a getter is defined
		# in the methods.
		#

		$getter = static::find_method_callback('__volatile_get_' . $property);

		if ($getter)
		{
			return call_user_func($getter, $this, $property);
		}

		$getter = static::find_method_callback('__get_' . $property);

		if ($getter)
		{
			return $this->$property = call_user_func($getter, $this, $property);
		}

		#
		#
		#

		$rc = $this->__defer_get($property, $success);

		if ($success)
		{
			return $this->$property = $rc;
		}

		$reflexion_class = new \ReflectionClass($this);

		try
		{
			$reflexion_property = $reflexion_class->getProperty($property);

			throw new Exception\PropertyNotReadable(array($property, $this));
		}
		catch (\ReflectionException $e) { }

		$properties = array_keys(get_object_vars($this));

		if ($properties)
		{
			throw new Exception\PropertyNotFound
			(
				format
				(
					'Unknown or unaccessible property %property for object of class %class (available properties: !list).', array
					(
						'property' => $property,
						'class' => get_class($this),
						'list' => implode(', ', $properties)
					)
				)
			);
		}

		throw new Exception\PropertyNotFound(array($property, $this));
	}

	protected function __defer_get($property, &$success)
	{
		global $core;

		if (empty($core))
		{
			return;
		}

		$event = new \ICanBoogie\Object\PropertyEvent($this, array('property' => $property));

		#
		# The operation is considered a success if the `value` property exists in the event
		# object. Thus, even a `null` value is considered a success.
		#

		if (!property_exists($event, 'value'))
		{
			return;
		}

		$success = true;

		return $event->value;
	}

	/**
	 * Sets the value of inaccessible properties.
	 *
	 * If the `__volatile_set_<property>` or `__set_<property>` setter methods exists, they are
	 * used to set the value to the property, otherwise the value is set _as is_.
	 *
	 * For performance reason, external setters are not used.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	public function __set($property, $value)
	{
		$setter = '__volatile_set_' . $property;

		if (method_exists($this, $setter))
		{
			return $this->$setter($value);
		}

		$setter = '__set_' . $property;

		if (method_exists($this, $setter))
		{
			return $this->$property = $this->$setter($value);
		}

		/*
		$setter = static::find_method_callback('__volatile_set_' . $property);

		if ($setter)
		{
			return call_user_func($setter, $this, $property, $value);
		}

		$setter = static::find_method_callback('__set_' . $property);

		if ($setter)
		{
			return $this->$property = call_user_func($setter, $this, $property, $value);
		}
		*/

		#
		# Because property_exists() check class properties, it return true even when the property
		# has been unset, thus we need to check if the property still exists using
		# get_object_vars(). We use them both because property_exists() doesn't cost much and will
		# sometimes suffice.
		#

		if (property_exists($this, $property))
		{
			$properties = get_object_vars($this);

			if (array_key_exists($property, $properties))
			{
				throw new Exception\PropertyNotWritable(array($property, $this));
			}
		}

		$this->$property = $value;
	}

	/**
	 * Checks if the object has the specified property.
	 *
	 * Unlike the property_exists() function, this method uses all the getters available to find
	 * the property.
	 *
	 * @param string $property The property to check.
	 *
	 * @return bool true if the object has the property, false otherwise.
	 */
	public function has_property($property)
	{
		if (property_exists($this, $property))
		{
			return true;
		}

		$getter = '__volatile_get_' . $property;

		if (method_exists($this, $getter))
		{
			return true;
		}

		$getter = static::find_method_callback($getter);

		if ($getter)
		{
			return true;
		}

		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return true;
		}

		$getter = static::find_method_callback($getter);

		if ($getter)
		{
			return true;
		}

		$rc = $this->__defer_get($property, $success);

		if ($success)
		{
			$this->$property = $rc;

			return true;
		}

		return false;
	}

	/**
	 * Checks whether this object supports the specified method.
	 *
	 * @param string $method Name of the method.
	 *
	 * @return bool true if the object supports the method, false otherwise.
	 */
	public function has_method($method)
	{
		return method_exists($this, $method) || static::find_method_callback($method);
	}

	/**
	 * Returns the callback for a given unimplemented method.
	 *
	 * @param $method
	 *
	 * @return mixed Callback for the given unimplemented method, or null if none is defined.
	 */
	protected static function find_method_callback($method)
	{
		global $core;

		$methods = static::find_external_methods();

		if (empty($methods[$method]))
		{
			return;
		}

		return $methods[$method];
	}
}

namespace ICanBoogie\Object;

/**
 * Event class for the `ICanBoogie\Object::property` event.
 */
class PropertyEvent extends \ICanBoogie\Event
{
	/**
	 * Name of the property to retrieve.
	 *
	 * @var string
	 */
	public $property;

	/**
	 * The event is created with the type `property`.
	 *
	 * @param Object $target
	 * @param array $properties
	 */
	public function __construct(\ICanBoogie\Object $target, array $properties, $type='property')
	{
		parent::__construct($target, $properties, $type);
	}
}