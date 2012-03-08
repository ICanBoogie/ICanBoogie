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
	/*
	 * HTTP methods as defined by the {@link http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html Hypertext Transfert protocol 1.1}.
	 */
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_GET = 'GET';
	const METHOD_HEAD = 'HEAD';
	const METHOD_POST = 'POST';
	const METHOD_PUT = 'PUT';
	const METHOD_PATCH = 'PATCH';
	const METHOD_DELETE = 'DELETE';
	const METHOD_TRACE = 'TRACE';
	const METHOD_CONNECT = 'CONNECT';

	static protected $methods = array
	(
		self::METHOD_DELETE,
		self::METHOD_GET,
		self::METHOD_HEAD,
		self::METHOD_OPTIONS,
		self::METHOD_POST,
		self::METHOD_PUT,
		self::METHOD_PATCH,
		self::METHOD_TRACE
	);

	protected $env;

	public $pathinfo_parameters = array();
	public $query_parameters = array();
	public $request_parameters = array();
	public $params;
	public $cookies = array();

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
			$this->params = $this->pathinfo_parameters + $this->request_parameters + $this->query_parameters;
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

		return $operation($this);
	}

	/**
	 * Overrides the method to provide a virtual method for each request method.
	 *
	 * Example:
	 *
	 * Request::from(array('pathinfo' => '/api/core/aloha'))->get();
	 *
	 * @see ICanBoogie.Object::__call()
	 */
	public function __call($method, $arguments)
	{
		$http_method = strtoupper($method);

		if (in_array($http_method, self::$methods))
		{
			array_unshift($arguments, $http_method);

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

	/**
	 * Returns the sciprt name.
	 *
	 * The setter is volatile, the value is returned from the ENV key `SCIPT_NAME`.
	 *
	 * @return string
	 */
	protected function __volatile_get_script_name()
	{
		return $this->env['SCRIPT_NAME'];
	}

	/**
	 * Sets the script name.
	 *
	 * The setter is volatile, the value is set to the ENV key `SCRIPT_NAME`.
	 *
	 * @param string $value
	 */
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

		if ($method == self::METHOD_POST && !empty($this->request_parameters['_method']))
		{
			$method = strtoupper($this->request_parameters['_method']);
		}

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

	/**
	 * Checks if the request method is DELETE.
	 *
	 * @return boolean
	 */
	protected function __get_is_delete()
	{
		return $this->method == 'delete';
	}

	/**
	 * Checks if the request method is GET.
	 *
	 * @return boolean
	 */
	protected function __get_is_get()
	{
		return $this->method == self::METHOD_GET;
	}

	/**
	 * Checks if the request method is HEAD.
	 *
	 * @return boolean
	 */
	protected function __get_is_head()
	{
		return $this->method == self::METHOD_HEAD;
	}

	/**
	 * Checks if the request method is OPTIONS.
	 *
	 * @return boolean
	 */
	protected function __get_is_options()
	{
		return $this->method == self::METHOD_OPTIONS;
	}

	/**
	 * Checks if the request method is PATCH.
	 *
	 * @return boolean
	 */
	protected function __get_is_patch()
	{
		return $this->method == self::METHOD_PATCH;
	}

	/**
	 * Checks if the request method is POST.
	 *
	 * @return boolean
	 */
	protected function __get_is_post()
	{
		return $this->method == self::METHOD_POST;
	}

	/**
	 * Checks if the request method is PUT.
	 *
	 * @return boolean
	 */
	protected function __get_is_put()
	{
		return $this->method == self::METHOD_PUT;
	}

	/**
	 * Checks if the request method is TRACE.
	 *
	 * @return boolean
	 */
	protected function __get_is_trace()
	{
		return $this->method == self::METHOD_TRACE;
	}

	/**
	 * Checks if the request is a XMLHTTPRequest.
	 *
	 * @return boolean
	 */
	protected function __get_is_xhr()
	{
		return !empty($this->env['HTTP_X_REQUESTED_WITH']) && preg_match('/XMLHttpRequest/', $this->env['HTTP_X_REQUESTED_WITH']);
	}

	/**
	 * Checks if the request is local.
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

	protected function __volatile_get_uri()
	{
		return $this->env['REQUEST_URI'];
	}

	protected function __volatile_set_uri($uri)
	{
		unset($this->pathinfo);
		unset($this->query_string);

		$this->env['REQUEST_URI'] = $uri;
	}

	protected function __get_pathinfo()
	{
		$path = $this->env['REQUEST_URI'];
		$qs = $this->query_string;

		if ($qs)
		{
			$path = substr($path, 0, -(strlen($qs) + 1));
		}

		return $path;
	}

	/**
	 * Returns the extension of the path info.
	 *
	 * @return mixed
	 */
	protected function __volatile_get_extension()
	{
		return pathinfo($this->pathinfo, PATHINFO_EXTENSION);
	}

	protected function __get_params()
	{
		return $this->pathinfo_parameters + $this->request_parameters + $this->query_parameters;
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