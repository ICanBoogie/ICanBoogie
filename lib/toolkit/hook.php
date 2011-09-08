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

class Hook
{
	static protected $hooks = array();

	static public function config_constructor($configs)
	{
		$by_ns = array();

		foreach ($configs as $config)
		{
			foreach ($config as $namespace => $hooks)
			{
				if (isset($hooks[0]))
				{
					//DIRTY-20100621: COMPAT

					wd_log('COMPAT: double array no longer needed: \1', array($hooks));

					$hooks = array_shift($hooks);
				}

				foreach ($hooks as $name => $definition)
				{
					$by_ns[$namespace . '/' . $name] = $definition;
				}
			}
		}

		#
		# the (object) cast is a workaround for an APC bug: http://pecl.php.net/bugs/bug.php?id=8118
		#

		return (object) $by_ns;
	}

	static public function find($ns, $name)
	{
		global $core;

		if (!self::$hooks)
		{
			#
			# the (array) cast is a workaround for an APC bug: http://pecl.php.net/bugs/bug.php?id=8118
			#

			self::$hooks = (array) $core->configs->synthesize('hooks', __CLASS__ . '::config_constructor');
		}

		if (empty(self::$hooks[$ns . '/' . $name]))
		{
			throw new Exception('Undefined hook %name in namespace %ns', array('%name' => $name, '%ns' => $ns));
		}

		$hook = self::$hooks[$ns . '/' . $name];

		#
		# `$hook` is an array when the hook has not been created yet, in which case we create the
		# hook on the fly.
		#

		if (is_array($hook))
		{
			$tags = $hook;

			list($callback, $params) = $tags + array(1 => array());

			unset($tags[0]);
			unset($tags[1]);

			if (is_string($callback) && $callback[1] == ':' && $callback[0] == 'o')
			{
				$class = substr($callback, 2);

				$hook = new $class();
				$hook->params = $params;
				$hook->tags = $tags;
			}
			else
			{
				$hook = new Hook($callback, $params, $tags);
			}

			self::$hooks[$ns . '/' . $name] = $hook;
		}

		return $hook;
	}

	public $callback;
	public $params = array();
	public $tags = array();

	public function __construct($callback, array $params=array(), array $tags=array())
	{
		$this->callback = $callback;
		$this->params = $params;
		$this->tags = $tags;
	}

	public function __invoke()
	{
		$args = func_get_args();

		return call_user_func_array($this->callback, $args);
	}
}