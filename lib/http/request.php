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
 * @property-read boolean $authorization Authorization of the request.
 * @property-read int $content_length Length of the request content.
 * @property-read int $cache_control A {@link \ICanBoogie\HTTP\Headers\CacheControl} object.
 * @property-read string $ip Remote IP of the request.
 * @property-read boolean $is_delete Is this a `DELETE` request?
 * @property-read boolean $is_get Is this a `GET` request?
 * @property-read boolean $is_head Is this a `HEAD` request?
 * @property-read boolean $is_options Is this a `OPTIONS` request?
 * @property-read boolean $is_patch Is this a `PATCH` request?
 * @property-read boolean $is_post Is this a `POST` request?
 * @property-read boolean $is_put Is this a `PUT` request?
 * @property-read boolean $is_trace Is this a `TRACE` request?
 * @property-read boolean $is_local Is this a local request?
 * @property-read boolean $is_xhr Is this an Ajax request?
 * @property-read string $method Method of the request.
 * @property-read string $normalized_path Path of the request normalized using the {@link \ICanBoogie\normalize_url_path()} function.
 * @property-read Request $previous Previous request.
 * @property-read string $query_string Query string of the request.
 * @property-read string $referer Referer of the request.
 * @property-read string $user_agent User agent of the request.
 * @property string $uri URI of the request.
 *
 * @see http://en.wikipedia.org/wiki/Uniform_resource_locator
 */
class Request extends Object implements \ArrayAccess, \IteratorAggregate
{
	/*
	 * HTTP methods as defined by the {@link http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html Hypertext Transfert protocol 1.1}.
	 */
	const METHOD_ANY = 'ANY';
	const METHOD_CONNECT = 'CONNECT';
	const METHOD_DELETE = 'DELETE';
	const METHOD_GET = 'GET';
	const METHOD_HEAD = 'HEAD';
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_POST = 'POST';
	const METHOD_PUT = 'PUT';
	const METHOD_PATCH = 'PATCH';
	const METHOD_TRACE = 'TRACE';

	static protected $methods = array
	(
		self::METHOD_CONNECT,
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
	 * Current request.
	 *
	 * @var Request
	 */
	static protected $current_request;

	/**
	 * Returns the current request.
	 *
	 * @return Request
	 */
	static public function get_current_request()
	{
		return self::$current_request;
	}

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
	 * Union of {@link $path_params}, {@link $request_params} and {@link $query_params}.
	 *
	 * @var array
	 */
	public $params;

	/**
	 * General purpose container.
	 *
	 * @var Request\Context
	 */
	public $context;

	/**
	 * The headers of the request.
	 *
	 * @var Headers
	 */
	public $headers;

	/**
	 * Request environment.
	 *
	 * @var array
	 */
	protected $env;

	/**
	 * Previous request.
	 *
	 * @var Request
	 */
	protected $previous;

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
	static public function from($properties=null, array $construct_args=array(), $class_name=null)
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
	 * Initialize the properties {@link $env}, {@link $headers} and {@link $context}.
	 *
	 * If the {@link $params} property is `null` it is set with an usinon of {@link $path_params},
	 * {@link $request_params} and {@link $query_params}.
	 *
	 * @param array $env Environment of the request, usually the `$_SERVER` super global.
	 */
	protected function __construct(array $env=array())
	{
		$this->env = $env;
		$this->headers = new Headers($env);
		$this->context = new Request\Context($this);

		if ($this->params === null)
		{
 			$this->params = $this->path_params + $this->request_params + $this->query_params;
		}
	}

	/**
	 * Dispatch the request.
	 *
	 * The {@link previous} property is used for request chaining. The {@link $current_request}
	 * class property is set to the current request.
	 *
	 * @param string|null $method The request method. Use this parameter to override the request
	 * method.
	 * @param array|null $params The request parameters. Use this parameter to override the request
	 * parameters. The {@link $path_params}, {@link $query_params} and
	 * {@link $request_params} are set to empty arrays. The provided parameters are set to the
	 * {@link $params} property.
	 *
	 * @return Response The response to the request.
	 *
	 * @throws \Exception
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

// 		if ($this->previous) // TODO-20120831: This is a workaround
		{
			self::$current_request = $this->previous;
		}

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
	 *     Request::from(array('pathinfo' => '/api/core/aloha'))->get();
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
	 * Returns the previous request.
	 *
	 * @return \ICanBoogie\HTTP\Request
	 */
	protected function volatile_get_previous()
	{
		return $this->previous;
	}

	/**
	 * Returns the `Cache-Control` header.
	 *
	 * @return \ICanBoogie\HTTP\Headers\CacheControl
	 */
	protected function volatile_get_cache_control()
	{
		return $this->headers['Cache-Control'];
	}

	/**
	 * Sets the directives of the `Cache-Control` header.
	 *
	 * @param string $cache_directives
	 */
	protected function volatile_set_cache_control($cache_directives)
	{
		$this->headers['Cache-Control'] = $cache_directives;
	}

	/**
	 * Returns the script name.
	 *
	 * The setter is volatile, the value is returned from the ENV key `SCRIPT_NAME`.
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
	 * @throws UnsupportedMethodException when the request method is not supported.
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
			throw new UnsupportedMethodException('Unsupported request method: %method', array('method' => $method));
		}

		return $method;
	}

	/**
	 * Sets the `QUERY_STRING` value of the {@link $env} array.
	 *
	 * @param string $query_string
	 */
	protected function volatile_set_query_string($query_string)
	{
		$this->env['QUERY_STRING'] = $query_string;
	}

	/**
	 * Returns the `QUERY_STRING` value of the {@link $env} array.
	 *
	 * @param string $query_string The method returns `null` if the key is not defined.
	 */
	protected function volatile_get_query_string()
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
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_delete}.
	 */
	protected function volatile_set_is_delete()
	{
		throw new Exception\PropertyNotWritable(array('is_delete', $this));
	}

	/**
	 * Checks if the request method is `DELETE`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_delete()
	{
		return $this->method == 'delete';
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_get}.
	 */
	protected function volatile_set_is_get()
	{
		throw new Exception\PropertyNotWritable(array('is_get', $this));
	}

	/**
	 * Checks if the request method is `GET`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_get()
	{
		return $this->method == self::METHOD_GET;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_head}.
	 */
	protected function volatile_set_is_head()
	{
		throw new Exception\PropertyNotWritable(array('is_head', $this));
	}

	/**
	 * Checks if the request method is `HEAD`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_head()
	{
		return $this->method == self::METHOD_HEAD;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_options}.
	 */
	protected function volatile_set_is_options()
	{
		throw new Exception\PropertyNotWritable(array('is_options', $this));
	}

	/**
	 * Checks if the request method is `OPTIONS`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_options()
	{
		return $this->method == self::METHOD_OPTIONS;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_patch.
	 */
	protected function volatile_set_is_patch()
	{
		throw new Exception\PropertyNotWritable(array('is_patch', $this));
	}

	/**
	 * Checks if the request method is `PATCH`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_patch()
	{
		return $this->method == self::METHOD_PATCH;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_post}.
	 */
	protected function volatile_set_is_post()
	{
		throw new Exception\PropertyNotWritable(array('is_post', $this));
	}

	/**
	 * Checks if the request method is `POST`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_post()
	{
		return $this->method == self::METHOD_POST;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_put}.
	 */
	protected function volatile_set_is_put()
	{
		throw new Exception\PropertyNotWritable(array('is_put', $this));
	}

	/**
	 * Checks if the request method is `PUT`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_put()
	{
		return $this->method == self::METHOD_PUT;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_trace}.
	 */
	protected function volatile_set_is_trace()
	{
		throw new Exception\PropertyNotWritable(array('is_trace', $this));
	}

	/**
	 * Checks if the request method is `TRACE`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_trace()
	{
		return $this->method == self::METHOD_TRACE;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_xhr}.
	 */
	protected function volatile_set_is_xhr()
	{
		throw new Exception\PropertyNotWritable(array('is_xhr', $this));
	}

	/**
	 * Checks if the request is a `XMLHTTPRequest`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_xhr()
	{
		return !empty($this->env['HTTP_X_REQUESTED_WITH']) && preg_match('/XMLHttpRequest/', $this->env['HTTP_X_REQUESTED_WITH']);
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_local}.
	 */
	protected function volatile_set_is_local()
	{
		throw new Exception\PropertyNotWritable(array('is_local', $this));
	}

	/**
	 * Checks if the request is local.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_local()
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
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $ip}.
	 */
	protected function volatile_set_ip()
	{
		throw new Exception\PropertyNotWritable(array('ip', $this));
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
	protected function volatile_get_ip()
	{
		if (isset($this->env['HTTP_X_FORWARDED_FOR']))
		{
			$addr = $this->env['HTTP_X_FORWARDED_FOR'];

			list($addr) = explode(',', $addr);

			return $addr;
		}

		return $this->env['REMOTE_ADDR'] ?: '::1';
	}


	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $authorization}.
	 */
	protected function volatile_set_authorization()
	{
		throw new Exception\PropertyNotWritable(array('authorization', $this));
	}

	protected function volatile_get_authorization()
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

	/**
	 * Sets the `REQUEST_URI` environment key.
	 *
	 * The {@link path} and {@link query_string} property are unset so that they are updated on
	 * there next access.
	 *
	 * @param string $uri
	 */
	protected function volatile_set_uri($uri)
	{
		unset($this->path);
		unset($this->query_string);

		$this->env['REQUEST_URI'] = $uri;
	}

	/**
	 * Returns the `REQUEST_URI` environment key.
	 *
	 * @return string
	 */
	protected function volatile_get_uri()
	{
		return isset($this->env['REQUEST_URI']) ? $this->env['REQUEST_URI'] : $_SERVER['REQUEST_URI'];
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
	 * Returns the port of the request.
	 *
	 * @return int
	 */
	protected function volatile_get_port()
	{
		return $this->env['REQUEST_PORT'];
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $path}.
	 */
	protected function volatile_set_path()
	{
		throw new Exception\PropertyNotWritable(array('path', $this));
	}

	/**
	 * Returns the path of the request, that is the `REQUEST_URI` without the query string.
	 *
	 * @return string
	 */
	protected function volatile_get_path()
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
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $normalized_path}
	 */
	protected function volatile_set_normalized_path()
	{
		throw new Exception\PropertyNotWritable(array('normalized_path', $this));
	}

	/**
	 * Returns the {@link $path} property normalized using the
	 * {@link \ICanBoogie\normalize_url_path()} function.
	 *
	 * @return string
	 */
	protected function volatile_get_normalized_path()
	{
		return \ICanBoogie\normalize_url_path($this->path);
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $extension}.
	 */
	protected function volatile_set_extension()
	{
		throw new Exception\PropertyNotWritable(array('extension', $this));
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

	protected function set_params($params)
	{
		return $params;
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
	 * @throws Exception\PropertyNotWritable in attempt to write an unsupported property.
	 */
	/*
	protected function last_chance_set($property, $value, &$success)
	{
		throw new Exception\PropertyNotWritable(array($property, $this));
	}
	*/
}

namespace ICanBoogie\HTTP\Request;

/**
 * The context of a request.
 *
 * This is a general purpose container used to store the objects and variables related to a
 * request.
 *
 * @property-read \ICanBoogie\HTTP\Request $request The request associated with the context.
 */
class Context extends \ICanBoogie\Object
{
	/**
	 * The request the context belongs to.
	 *
	 * The variable is declared as private but is actually readable thanks to the
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

	/**
	 * @throws \ICanBoogie\Exception\PropertyNotWritable in attempt to write {@link $request}
	 */
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