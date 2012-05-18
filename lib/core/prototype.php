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
 *     'ICanBoogie\ActiveRecord\Page::get_my_property' => 'MyHookClass::get_my_property'
 * );
 */
class Prototype implements \ArrayAccess, \IteratorAggregate
{
	/**
	 * Callback to initialize prototypes.
	 *
	 * @var callable
	 */
	public static $initializer;

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

	public static function configure(array $config)
	{
		self::$pool = $config;

		foreach (self::$prototypes as $class => $prototype)
		{
			$prototype->revoke_consolided_methods();

			if (empty($config[$class]))
			{
				continue;
			}

			if ($prototype->methods)
			{
				echo "overriding prototype methods:"; var_dump($prototype->methods);
			}

			$prototype->methods = $config[$class];
		}
	}

	/**
	 * Synthesizes the prototype methods from the "hooks" config.
	 *
	 * @throws \InvalidArgumentException if a method definition is missing the '::' separator.
	 *
	 * @return array[string]callable
	 */
	public static function synthesize_config(array $fragments)
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
	}

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
	 * Creates a prototype for the specified class.
	 *
	 * @param string $class
	 */
	protected function __construct($class)
	{
		$this->class = $class;

		$parent_class = get_parent_class($class);

		if ($parent_class)
		{
			$this->parent = static::get($parent_class);
		}

		if (isset(self::$pool[$class]))
		{
			$this->methods = self::$pool[$class];
		}
	}

	/*
	protected function check_initialization()
	{
		return;

		if (self::$pool || !self::$initializer)
		{
			return;
		}

		trigger_error(__FUNCTION__);

		var_dump(self::$pool, self::$initializer);

		self::$pool = $pool = call_user_func(self::$initializer, $this);

		foreach (self::$prototypes as $class => $prototype)
		{
			$prototype->revoke_consolided_methods();

			if (empty($pool[$class]))
			{
				continue;
			}

			if ($prototype->methods)
			{
				echo "overriding prototype methods:"; var_dump($prototype->methods);
			}

			$prototype->methods = $pool[$class];
		}

		var_dump(self::$prototypes);
	}
	*/

	/**
	 * Consolide the methods of the prototype.
	 *
	 * The method creates a single array from the prototype methods and those of its parents.
	 *
	 * @return array[string]callable
	 */
	protected function get_consolided_methods()
	{
// 		$this->check_initialization();

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
