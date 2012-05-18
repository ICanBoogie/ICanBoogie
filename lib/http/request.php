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
 * An HTTP request.
 *
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
 *
 * @see http://en.wikipedia.org/wiki/Uniform_resource_locator
 * @see http://en.wikipedia.org/wiki/URL_normalization
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
	const METHOD_ANY = 'ANY';

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

	/**
	 * The request being executed.
	 *
	 * @var Request
	 */
	protected static $current_request;

	/**
	 * Returns the current request being executed.
	 *
	 * @return Request
	 */
	public static function get_current_request()
	{
		return self::$current_request;
	}

	/**
	 * Request environment.
	 *
	 * @var arrays
	 */
	protected $env;

	/**
	 * Parameters extracted from the request path.
	 *
	 * @var array
	 */
	public $path_params = array();

	/**
	 * Parameters defined by the query string.
	 *
	 * @var array
	 */
	public $query_params = array();

	/**
	 * Parameters defined by the request body.
	 *
	 * @var array
	 */
	public $request_params = array();

	/**
	 * Union of {@link $path_params}, {@link $request_params} and
	 * {@link $query_params}.
	 *
	 * @var array
	 */
	public $params;

	public $cookies = array();

	/**
	 * The previous request being executed.
	 *
	 * @var Request
	 */
	public $previous;

	/**
	 * General purpose container.
	 *
	 * @var Request\Context
	 */
	public $context;

	/**
	 * A request can be created from the `$_SERVER` super global array. In that case `$_SERVER` is
	 * used as environment and the request is created with the following properties:
	 *
	 * - {@link $cookie}: a reference to the `$_COOKIE` super global array.
	 * - {@link $path_params}: initialized to an empty array.
	 * - {@link $query_params}: a reference to the `$_GET` super global array.
	 * - {@link $request_params}: a reference to the `$_POST` super global array.
	 *
	 * @param array $properties
	 * @param array $construct_args
	 * @param string $class_name
	 *
	 * @return Request
	 */
	public static function from($properties=null, array $construct_args=array(), $class_name=null)
	{
		if (is_string($properties))
		{
			$properties = array
			(
				'path' => $properties
			);
		}
		else if ($properties == $_SERVER)
		{
			return parent::from
			(
				array
				(
					'cookies' => &$_COOKIE,
					'path_params' => array(),
					'query_params' => &$_GET,
					'request_params' => &$_POST
				),

				array($_SERVER)
			);
		}

		return parent::from($properties, $construct_args, $class_name);
	}

	/**
	 * @param array $env Environment of the request, usually the `$_SERVER` super global.
	 */
	protected function __construct(array $env=array())
	{
		$this->env = $env;

		if ($this->params === null)
		{
 			$this->params = $this->path_params + $this->request_params + $this->query_params;
		}

		$this->context = new Request\Context($this);
	}

	/**
	 * Dispatch the request.
	 *
	 * The {@link previous} property is used for request chaining. The {@link current_request}
	 * class property is set to the current request.
	 *
	 * @param string|null $method The request method. Use this parameter to override the request
	 * method.
	 * @param array|null $params The request parameters. Use this parameter to override the request
	 * parameters. The {@link path_params}, {@link query_params} and
	 * {@link request_params} are set to empty arrays. The provided parameters are set to the
	 * {@link params} property.
	 *
	 * @return Response The response to the request.
	 */
	public function __invoke($method=null, $params=null)
	{
		global $core;

		if ($method !== null)
		{
			$this->method = $method;
		}

		if ($params !== null)
		{
			$this->path_params = array();
			$this->query_params = array();
			$this->request_params = array();
			$this->params = $params;
		}

		$this->previous = self::$current_request;

		self::$current_request = $this;

		try
		{
			$response = dispatch($this);
		}
		catch (\Exception $e) { }

		self::$current_request = $this->previous;

		if (isset($e))
		{
			throw $e;
		}

		return $response;
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
		return isset($this->params[$param]);
	}

	/**
	 * Get the specified param from the request.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($param)
	{
		return isset($this->params[$param]) ? $this->params[$param] : null;
	}

	/**
	 * Set the specified param to the specified value.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($param, $value)
	{
		$this->params[$param] = $value;
	}

	/**
	 * Remove the specified param from the request.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($param)
	{
		unset($this->params[$param]);
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
	protected function volatile_get_script_name()
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
	protected function volatile_set_script_name($value)
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
	protected function get_method()
	{
		$method = $this->env['REQUEST_METHOD'];

		if ($method == self::METHOD_POST && !empty($this->request_params['_method']))
		{
			$method = strtoupper($this->request_params['_method']);
		}

		if (!in_array($method, self::$methods))
		{
			throw new Exception('Unsupported request method: %method', array('%method' => $method), 400);
		}

		return $method;
	}

	protected function get_query_string()
	{
		return isset($this->env['QUERY_STRING']) ? $this->env['QUERY_STRING'] : null;
	}

	protected function get_content_length()
	{
		return isset($this->env['CONTENT_LENGTH']) ? $this->env['CONTENT_LENGTH'] : null;
	}

	protected function get_referer()
	{
		return isset($this->env['HTTP_REFERER']) ? $this->env['HTTP_REFERER'] : null;
	}

	protected function get_user_agent()
	{
		return isset($this->env['HTTP_USER_AGENT']) ? $this->env['HTTP_USER_AGENT'] : null;
	}

	/**
	 * Checks if the request method is `DELETE`.
	 *
	 * @return boolean
	 */
	protected function get_is_delete()
	{
		return $this->method == 'delete';
	}

	/**
	 * Checks if the request method is `GET`.
	 *
	 * @return boolean
	 */
	protected function get_is_get()
	{
		return $this->method == self::METHOD_GET;
	}

	/**
	 * Checks if the request method is `HEAD`.
	 *
	 * @return boolean
	 */
	protected function get_is_head()
	{
		return $this->method == self::METHOD_HEAD;
	}

	/**
	 * Checks if the request method is `OPTIONS`.
	 *
	 * @return boolean
	 */
	protected function get_is_options()
	{
		return $this->method == self::METHOD_OPTIONS;
	}

	/**
	 * Checks if the request method is `PATCH`.
	 *
	 * @return boolean
	 */
	protected function get_is_patch()
	{
		return $this->method == self::METHOD_PATCH;
	}

	/**
	 * Checks if the request method is `POST`.
	 *
	 * @return boolean
	 */
	protected function get_is_post()
	{
		return $this->method == self::METHOD_POST;
	}

	/**
	 * Checks if the request method is `PUT`.
	 *
	 * @return boolean
	 */
	protected function get_is_put()
	{
		return $this->method == self::METHOD_PUT;
	}

	/**
	 * Checks if the request method is `TRACE`.
	 *
	 * @return boolean
	 */
	protected function get_is_trace()
	{
		return $this->method == self::METHOD_TRACE;
	}

	/**
	 * Checks if the request is a `XMLHTTPRequest`.
	 *
	 * @return boolean
	 */
	protected function get_is_xhr()
	{
		return !empty($this->env['HTTP_X_REQUESTED_WITH']) && preg_match('/XMLHttpRequest/', $this->env['HTTP_X_REQUESTED_WITH']);
	}

	/**
	 * Checks if the request is local.
	 *
	 * @return boolean
	 */
	protected function get_is_local()
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
	 * Returns the remote IP of the request.
	 *
	 * If defined, the `HTTP_X_FORWARDED_FOR` header is used to retrieve the original IP.
	 *
	 * If the `REMOTE_ADDR` header is empty the request is considered local thus `::1` is returned.
	 *
	 * @see http://en.wikipedia.org/wiki/X-Forwarded-For
	 *
	 * @return string
	 */
	protected function get_ip()
	{
		if (isset($this->env['HTTP_X_FORWARDED_FOR']))
		{
			$addr = $this->env['HTTP_X_FORWARDED_FOR'];

			list($addr) = explode(',', $addr);

			return $addr;
		}

		return $this->env['REMOTE_ADDR'] ?: '::1';
	}

	protected function get_authorization()
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

	protected function volatile_get_uri()
	{
		return $this->env['REQUEST_URI'];
	}

	protected function volatile_set_uri($uri)
	{
		unset($this->path);
		unset($this->query_string);

		$this->env['REQUEST_URI'] = $uri;
	}

	/**
	 * Returns the port of the request.
	 *
	 * @return int
	 */
	protected function volatile_get_port()
	{
		return $this->env['REQUEST_PORT'];
	}

	/**
	 * Sets the port of the request.
	 *
	 * @param int $port
	 */
	protected function volatile_set_port($port)
	{
		$this->env['REQUEST_PORT'] = $port;
	}

	/**
	 * Returns the path of the request, that is the `REQUEST_URI` without the query string.
	 *
	 * @return string
	 */
	protected function get_path()
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
	protected function volatile_get_extension()
	{
		return pathinfo($this->path, PATHINFO_EXTENSION);
	}

	/**
	 * Returns the union of the {@link path_params}, {@link request_params} and
	 * {@link query_params} properties.
	 *
	 * This method is the getter of the {@link $params} magic property.
	 *
	 * @return array
	 */
	protected function get_params()
	{
		return $this->path_params + $this->request_params + $this->query_params;
	}

	/**
	 * Returns the headers of the request.
	 *
	 * @return Headers
	 */
	protected function get_headers()
	{
		return new Headers($this->env);
	}

	protected function get_files()
	{
		// TODO:2012-03-12 returns the files associated with the request
	}
}

namespace ICanBoogie\HTTP\Request;

/**
 * The context of a request.
 *
 * This is a general purpose container used to store the objects and variables related to a
 * request.
 */
class Context extends \ICanBoogie\Object
{
	/**
	 * The request the context belongs to.
	 *
	 * The variable is declared as private but is actually readdable thanks to the
	 * {@link volatile_get_request} getter.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	private $request;

	/**
	 * Constructor.
	 *
	 * @param \ICanBoogie\HTTP\Request $request The request the context belongs to.
	 */
	public function __construct(\ICanBoogie\HTTP\Request $request)
	{
		$this->request = $request;
	}

	protected function volatile_set_request()
	{
		throw new \ICanBoogie\Exception\PropertyNotWritable(array('request', $this));
	}

	/**
	 * Returns the {@link $request} property.
	 *
	 * @return \ICanBoogie\HTTP\Request
	 */
	protected function volatile_get_request()
	{
		return $this->request;
	}
}