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
 * Together with the {@link Prototype} class the {@link Object} class provides means to
 * modify methods as well as define getters and setters for magic properties.
 *
 * The class also provides a method to create instances in the same fashion PDO creates instances
 * with the `FETCH_CLASS` mode, that is the properties of the instance are set *before* its
 * constructor is invoked.
 *
 * Getters and setters
 * -------------------
 *
 * When an innaccessible property is read or written the class tries to find a method suitable
 * to read or write the property. Theses methods are called getters and setters. They can be
 * defined by the class or its prototype. For example the `connection` property could have the
 * following getter and setter:
 *
 *     protected function __get_connection()
 *     {
 *         return new Connection($this->username, $this->password);
 *     }
 *
 *     protected function __set_connection(Connection $connection)
 *     {
 *         return $connection;
 *     }
 *
 * In this example the `connection` property is created after the `__get_connection()` is called,
 * which is an ideal behaviour to lazyload resources.
 *
 * Another type of getter/setter is available that doesn't create the requested property. They are
 * call _volatile_, because their result is not automatically stored in the corresponding property,
 * this choice is up to the setter.
 *
 *     namespace ICanBoogie;
 *
 *     class Time extends Object
 *     {
 *         public $seconds;
 *
 *         protected function __volatile_get_minutes()
 *         {
 *             return $this->seconds / 60;
 *         }
 *
 *         protected function __volatile_set_minutes($minutes)
 *         {
 *             $this->seconds = $minutes * 60;
 *         }
 *     }
 *
 * In this example the result of the `minutes` getter is simply returned and is not used to create
 * the `minutes` property
 *
 * Event: property
 * ---------------
 *
 * The `ICanBoogie\Object::property` event is fired when no getter was found in a class or
 * prototype to obtain the value of a property.
 *
 * Hooks can be attached to that event to provide the value of the property. Should they be able
 * to provide the value, they must create the `value` property within the event object. Thus, even
 * `null` is considered a valid result.
 *
 * @property-read Prototype $prototype The prototype associated with the class.
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

	/**
	 * Removes the {@link prototype} key before serialization.
	 *
	 * @return array
	 */
	public function __sleep()
	{
		$keys = array_keys(get_object_vars($this));
		$keys = array_combine($keys, $keys);

		unset($keys['prototype']);

		return $keys;
	}

	/**
	 * Use the prototype to provide callbacks for inaccessible methods.
	 *
	 * @param string $method
	 * @param array $arguments
	 *
	 * @return mixed The result of the callback.
	 */
	public function __call($method, $arguments)
	{
		array_unshift($arguments, $this);

		return call_user_func_array($this->prototype[$method], $arguments);
	}

	/**
	 * Returns the value of an inaccessible property.
	 *
	 * Multiple callbacks are tried in order to retrieve the value of the property:
	 *
	 * 1. `__volatile_get_<property>`: Get and return the value of the property.
	 * 2. `__get_<property>`: Get, set and return the value of the property. Because new properties
	 * are created as public the callback is only called once which is ideal for lazyloading.
	 * 3. The prototype is queried for callbacks for the `__volatile_get_<property>` and
	 * `__get_<property>` methods.
	 * 4.Finaly, the `ICanBoogie\Object::property` event is fired to try and retrieve the value of
	 * the property.
	 *
	 * @param string $property
	 *
	 * @throws Exception\PropertyNotReadable when the property has a protected or private scope and
	 * no suitable callback could be found to retrieve its value.
	 *
	 * @throws Exception\PropertyNotFound when the property is undefined and there is no suitable
	 * callback to retrieve its values.
	 *
	 * @return mixed The value of the inaccessible property.
	 */
	public function __get($property)
	{
		$method = '__volatile_get_' . $property;

		if (method_exists($this, $method))
		{
			return $this->$method();
		}

		$method = '__get_' . $property;

		if (method_exists($this, $method))
		{
			return $this->$property = $this->$method();
		}

		#
		# we didn't find a suitable method in the class, maybe the prototype has one.
		#

		$prototype = $this->prototype;

		$method = '__volatile_get_' . $property;

		if (isset($prototype[$method]))
		{
			return call_user_func($prototype[$method], $this, $property);
		}

		$method  = '__get_' . $property;

		if (isset($prototype[$method]))
		{
			return $this->$property = call_user_func($prototype[$method], $this, $property);
		}

		#
		# we didn't find a suitable method in the prototype, our last chance is to fire an event.
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
					'Unknown or inaccessible property %property for object of class %class (available properties: !list).', array
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

		$event = new Object\PropertyEvent($this, array('property' => $property));

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
	 * Returns the prototype associated with the class.
	 *
	 * @return Prototype
	 */
	protected function __get_prototype()
	{
		return Prototype::get($this);
	}

	/**
	 * Sets the value of inaccessible properties.
	 *
	 * If the `__volatile_set_<property>` or `__set_<property>` setter methods exists, they are
	 * used to set the value to the property, otherwise the value is set _as is_.
	 *
	 * For performance reason the prototype is not used to provide callbacks but this may change
	 * in the future.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	public function __set($property, $value)
	{
		$method = '__volatile_set_' . $property;

		if (method_exists($this, $method))
		{
			return $this->$method($value);
		}

		$method = '__set_' . $property;

		if (method_exists($this, $method))
		{
			return $this->$property = $this->$method($value);
		}

		#
		# Because property_exists() checks class properties it returns true even when the property
		# has been unset, thus we need to check if the property still exists using
		# get_object_vars(). We use them both because property_exists() doesn't cost much and will
		# sometime suffice.
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
	 * The difference with the `property_exists()` function is that the method also checks for
	 * getters defined by the class or the prototype.
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

		if ($this->has_method('__get_' . $property) || $this->has_method('__volatile_get_' . $property))
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
	 * The method checks for method defined by the class and the prototype.
	 *
	 * @param string $method Name of the method.
	 *
	 * @return bool
	 */
	public function has_method($method)
	{
		return method_exists($this, $method) || isset($this->prototype[$method]);
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
	public function __construct(\ICanBoogie\Object $target, array $properties)
	{
		parent::__construct($target, 'property', $properties);
	}
}

namespace ICanBoogie;

/**
 * Subclasses of the {@link Object} class are associated with a prototype, which can be used to
 * add methods as well as getters and setters to classes.
 *
 * Methods can be defined using the "hooks" config and the "prototype" namespace:
 *
 * <?php
 *
 * return array
 * (
 *     'ICanBoogie\ActiveRecord\Page::my_additional_method' => 'MyHookClass::my_additional_method',
 *     'ICanBoogie\ActiveRecord\Page::__get_my_property' => 'MyHookClass::get_my_property'
 * );
 */
class Prototype implements \ArrayAccess, \IteratorAggregate
{
	/**
	 * Class associated with the prototype.
	 *
	 * @var string
	 */
	protected $class;

	/**
	 * Parent prototype.
	 *
	 * @var Prototype
	 */
	protected $parent;

	/**
	 * Methods defined by the prototype.
	 *
	 * @var array[string]callable
	 */
	protected $methods = array();

	/**
	 * Methods defined by the prototypes chain.
	 *
	 * @var array[string]callable
	 */
	protected $consolided_methods;

	/**
	 * Prototypes built per class.
	 *
	 * @var array[string]Prototype
	 */
	protected static $prototypes = array();

	/**
	 * Pool of prototype methods per class.
	 *
	 * @var array[string]callable
	 */
	protected static $pool;

	/**
	 * Returns the prototype associated with the specified class or object.
	 *
	 * @param string|object $class
	 *
	 * @return Prototype
	 */
	public static function get($class)
	{
		if (is_object($class))
		{
			$class = get_class($class);
		}

		if (isset(self::$prototypes[$class]))
		{
			return self::$prototypes[$class];
		}

		self::$prototypes[$class] = $prototype = new static($class);

		return $prototype;
	}

	/**
	 * Creates a prototype for the specified class.
	 *
	 * @param string $class
	 */
	protected function __construct($class)
	{
		if (self::$pool === null)
		{
			self::$pool = static::synthesize_config();
		}

		$this->class = $class;

		$parent = null;

		if ($class != 'ICanBoogie\Object')
		{
			$parent_class = get_parent_class($class);
			$this->parent = $parent = static::get($parent_class);
		}

		$pool = self::$pool;

		if (isset($pool[$class]))
		{
			$this->methods = $pool[$class];
		}
	}

	/**
	 * Consolide the methods of the prototype.
	 *
	 * The method creates a single array from the prototype methods and those of its parents.
	 *
	 * @return array[string]callable
	 */
	protected function get_consolided_methods()
	{
		if ($this->consolided_methods !== null)
		{
			return $this->consolided_methods;
		}

		$methods = $this->methods;

		if ($this->parent)
		{
			$methods += $this->parent->get_consolided_methods();
		}

		return $this->consolided_methods = $methods;
	}

	/**
	 * Revokes the consolided methods of the prototype.
	 *
	 * The method must be invoked when prototype methods are modified.
	 */
	protected function revoke_consolided_methods()
	{
		$class = $this->class;

		foreach (self::$prototypes as $prototype)
		{
			if (!is_subclass_of($prototype->class, $class))
			{
				continue;
			}

			$prototype->consolided_methods = null;
		}
	}

	/**
	 * Adds or replaces the specified method of the prototype.
	 *
	 * @param string $method The name of the method.
	 *
	 * @param callable $callback
	 */
	public function offsetSet($method, $callback)
	{
 		self::$prototypes[$this->class]->methods[$method] = $callback;

		$this->revoke_consolided_methods();
	}

	/**
	 * Removed the specified method from the prototype.
	 *
	 * @param string $method The name of the method.
	 */
	public function offsetUnset($method)
	{
		unset(self::$prototypes[$this->class]->methods[$method]);

		$this->revoke_consolided_methods();
	}

	/**
	 * Checks if the prototype defines the specified method.
	 *
	 * @param string $method The name of the method.
	 *
	 * @return bool
	 */
	public function offsetExists($method)
	{
		$methods = $this->get_consolided_methods();

		return isset($methods[$method]);
	}

	/**
	 * Returns the callback associated with the specified method.
	 *
	 * @param string $method The name of the method.
	 *
	 * @throws Prototype\UnknownMethodException if the method is not defined.
	 *
	 * @return callable
	 */
	public function offsetGet($method)
	{
		$methods = $this->get_consolided_methods();

		if (!isset($methods[$method]))
		{
			throw new Prototype\UnknownMethodException(array($method, $this->class));
		}

		return $methods[$method];
	}

	/**
	 * Returns an iterator for the prototype methods.
	 *
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		$methods = $this->get_consolided_methods();

		return new \ArrayIterator($methods);
	}

	/**
	 * Synthesizes the prototype methods from the "hooks" config.
	 *
	 * @throws \InvalidArgumentException if a method definition is missing the '::' separator.
	 *
	 * @return array[string]callable
	 */
	protected static function synthesize_config()
	{
		global $core;

		return $core->configs->synthesize('prototypes', function(array $fragments)
		{
			$methods = array();

			foreach ($fragments as $root => $fragment)
			{
				if (empty($fragment['prototypes']))
				{
					continue;
				}

				foreach ($fragment['prototypes'] as $method => $callback)
				{
					if (strpos($method, '::') === false)
					{
						throw new \InvalidArgumentException(format
						(
							'Invalid method name %method, must be <code>class_name::method_name</code> in %pathname', array
							(
								'method' => $method,
								'pathname' => $root . 'config/hooks.php'
							)
						));
					}

					list($class, $method) = explode('::', $method);

					$methods[$class][$method] = $callback;
				}
			}

			return $methods;
		},

		'hooks');
	}
}

namespace ICanBoogie\Prototype;

/**
 * This exception is thrown when one tries to access an undefined prototype method.
 */
class UnknownMethodException extends \Exception
{
	public function __construct($message, $code=500, $previous=null)
	{
		if (is_array($message))
		{
			$message = \ICanBoogie\format('Undefined method %method for the prototype of the class %class.', array('method' => $message[0], 'class' => $message[1]));
		}

		parent::__construct($message, $code, $previous);
	}
}