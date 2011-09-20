<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\HTTP;

use ICanBoogie\Exception;
use ICanBoogie\Object;
use ICanBoogie\Operation;

/**
 * @property-read boolean $authorization
 * @property-read int $content_length
 * @property-read string $ip
 * @property-read boolean $is_delete
 * @property-read boolean $is_get
 * @property-read boolean $is_head
 * @property-read boolean $is_options
 * @property-read boolean $is_patch
 * @property-read boolean $is_post
 * @property-read boolean $is_put
 * @property-read boolean $is_trace
 * @property-read boolean $is_xhr
 * @property-read boolean $is_local
 * @property-read string $method
 * @property-read string $query_string
 * @property-read string $referer
 * @property-read string $user_agent
 */
class Request extends Object implements \ArrayAccess, \IteratorAggregate
{
	static protected $methods = array('delete', 'get', 'head', 'options', 'patch', 'post', 'put', 'trace');

	protected $env;

	public $path_parameters=array();
	public $query_parameters=array();
	public $request_parameters=array();
	public $params;
	public $cookies=array();

	public static function from_globals(array $properties=array())
	{
		return static::from
		(
			$properties + array
			(
				'cookies' => &$_COOKIE,
				'query_parameters' => &$_GET,
				'request_parameters' => &$_POST
			),

			array($_SERVER)
		);
	}

	protected function __construct($env=array())
	{
		$this->env = $env;

		$this->cookies = &$_COOKIE;
		$this->query_parameters = &$_GET;
		$this->request_parameters = &$_POST;

		if ($this->params === null)
		{
			$this->params = $this->path_parameters + $this->request_parameters + $this->query_parameters;
		}
	}

	public function __invoke($method=null, $params=null)
	{
		if ($method !== null)
		{
			$this->method = $method;
		}

		if ($params !== null)
		{
			$this->query_parameters = array();
			$this->request_parameters = array();
			$this->params = $params;
		}

		$operation = Operation::from_request($this);

		if (!$operation)
		{
			return;
		}

		$rc = $operation($this);

		if ($this->is_xhr)
		{
			exit;
		}

		return $rc;
	}

	/**
	 * Overrides the method to provide a virtual method for each request method.
	 *
	 * Example:
	 *
	 * Request::from(array('path' => '/api/core/aloha'))->get();
	 *
	 * @see ICanBoogie.Object::__call()
	 */
	public function __call($method, $arguments)
	{
		if (in_array($method, self::$methods))
		{
			array_unshift($arguments, $method);

			return call_user_func_array(array($this, '__invoke'), $arguments);
		}

		return parent::__call($method, $arguments);
	}

	/**
	 * Checks if the specified param exists in the request.
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($param)
	{
		return isset($this->params[(string) $param]);
	}

	/**
	 * Get the specified param from the request.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($param)
	{
		return isset($this->params[(string) $param]) ? $this->params[(string) $param] : null;
	}

	/**
	 * Set the specified param to the specified value.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($param, $value)
	{
		$this->params[(string) $param] = $value;
	}

	/**
	 * Remove the specified param from the request.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($param)
	{
		unset($this->params[(string) $param]);
	}

	/**
	 * Returns an array iterator for the params.
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->params);
	}

	protected function __volatile_get_script_name()
	{
		return $this->env['SCRIPT_NAME'];
	}

	protected function __volatile_set_script_name($value)
	{
		$this->env['SCRIPT_NAME'] = $value;
	}

	/**
	 * Returns the request's method.
	 *
	 * This is the getter for the `method` magic property.
	 *
	 * @throws Exception with code 400 when the request method is not supported.
	 *
	 * @return string
	 */
	protected function __get_method()
	{
		$method = $this->env['REQUEST_METHOD'];

		if ($method == 'POST' && !empty($this->request_parameters['_method']))
		{
			$method = $this->request_parameters['_method'];
		}

		$method = strtolower($method);

		if (!in_array($method, self::$methods))
		{
			throw new Exception('Unsupported request method: %method', array('%method' => $method), 400);
		}

		return $method;
	}

	protected function __get_query_string()
	{
		return isset($this->env['QUERY_STRING']) ? $this->env['QUERY_STRING'] : null;
	}

	protected function __get_content_length()
	{
		return isset($this->env['CONTENT_LENGTH']) ? $this->env['CONTENT_LENGTH'] : null;
	}

	protected function __get_referer()
	{
		return isset($this->env['HTTP_REFERER']) ? $this->env['HTTP_REFERER'] : null;
	}

	protected function __get_user_agent()
	{
		return isset($this->env['HTTP_USER_AGENT']) ? $this->env['HTTP_USER_AGENT'] : null;
	}

	protected function __get_is_delete()
	{
		return $this->method == 'delete';
	}

	protected function __get_is_get()
	{
		return $this->method == 'get';
	}

	protected function __get_is_head()
	{
		return $this->method == 'head';
	}

	protected function __get_is_options()
	{
		return $this->method == 'options';
	}

	protected function __get_is_patch()
	{
		return $this->method == 'patch';
	}

	protected function __get_is_post()
	{
		return $this->method == 'post';
	}

	protected function __get_is_put()
	{
		return $this->method == 'put';
	}

	protected function __get_is_trace()
	{
		return $this->method == 'trace';
	}

	/**
	 * Returns true if the request is a XMLHTTPRequest.
	 *
	 * @return boolean
	 */
	protected function __get_is_xhr()
	{
		return !empty($this->env['HTTP_X_REQUESTED_WITH']) && preg_match('/XMLHttpRequest/', $this->env['HTTP_X_REQUESTED_WITH']);
	}

	/**
	 * @see http://en.wikipedia.org/wiki/X-Forwarded-For
	 *
	 * @return string
	 */
	protected function __get_ip()
	{
		if (isset($this->env['HTTP_X_FORWARDED_FOR']))
		{
			$addr = $this->end['HTTP_X_FORWARDED_FOR'];

			list($addr) = explode(',', $addr);

			return $addr;
		}

		return isset($this->env['REMOTE_ADDR']) ? $this->env['REMOTE_ADDR'] : '::1';
	}

	/**
	 * Returns true if the request came from localhost, 127.0.0.1.
	 *
	 * @return boolean
	 */
    protected function __get_is_local()
	{
		static $patterns = array('::1', '/^127\.0\.0\.\d{1,3}$/', '/^0:0:0:0:0:0:0:1(%.*)?$/');

		$ip = $this->ip;

		foreach ($patterns as $pattern)
		{
			if ($pattern{0} == '/')
			{
				if (preg_match($pattern, $ip))
				{
					return true;
				}
			}
			else if ($pattern == $ip)
			{
				return true;
			}
		}

		return false;
	}

	protected function __get_authorization()
	{
		if (isset($this->env['HTTP_AUTHORIZATION']))
		{
			return $this->env['HTTP_AUTHORIZATION'];
		}
		else if (isset($this->env['X-HTTP_AUTHORIZATION']))
		{
			return $this->env['X-HTTP_AUTHORIZATION'];
		}
		else if (isset($this->env['X_HTTP_AUTHORIZATION']))
		{
			return $this->env['X_HTTP_AUTHORIZATION'];
		}
		else if (isset($this->env['REDIRECT_X_HTTP_AUTHORIZATION']))
		{
			return $this->env['REDIRECT_X_HTTP_AUTHORIZATION'];
		}
	}

	protected function __get_path()
	{
		$path = $this->env['REQUEST_URI'];
		$qs = $this->query_string;

		if ($qs)
		{
			$path = substr($path, 0, -(strlen($qs) + 1));
		}

		return $path;
	}

	protected function __get_params()
	{
		return $this->path_parameters + $this->request_parameters + $this->query_parameters;
	}

	protected function __get_headers()
	{
		return new Headers($this->env);
	}

	protected function __get_files()
	{
		return new Files();
	}
}