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

/**
 * The response to a HTTP request.
 *
 * @property \ICanBoogie\HTTP\Headers\DateTime $age {@link volatile_set_age()} {@link volatile_get_age()}
 * @property string $body {@link volatile_set_body()} {@link volatile_get_body()}
 * @property \ICanBoogie\HTTP\Headers\CacheControl $cache_control {@link volatile_set_cache_control()} {@link volatile_get_cache_control()}
 * @property string|array $content_length {@link volatile_set_content_length()} {@link volatile_get_content_length()}
 * @property string|array $content_type {@link volatile_set_content_type()} {@link volatile_get_content_type()}
 * @property \ICanBoogie\HTTP\Headers\DateTime $date {@link volatile_set_date()} {@link volatile_get_date()}
 * @property string $etag {@link volatile_set_etag()} {@link volatile_get_etag()}
 * @property \ICanBoogie\HTTP\Headers\DateTime $expires {@link volatile_set_expires()} {@link volatile_get_expires()}
 * @property \ICanBoogie\HTTP\Headers\DateTime $last_modified {@link volatile_set_last_modified()} {@link volatile_get_last_modified()}
 * @property string $location {@link volatile_set_location()} {@link volatile_get_location()}
 * @property integer $status {@link volatile_set_status()} {@link volatile_get_status()}
 * @property string $status_message {@link volatile_set_status_message()} {@link volatile_get_status_message()}
 * @property int $ttl {@link volatile_set_ttl()} {@link volatile_get_ttl()}
 *
 * @property-read boolean $is_cacheable {@link volatile_get_is_cacheable()}
 * @property-read boolean $is_client_error {@link volatile_get_is_client_error()}
 * @property-read boolean $is_empty {@link volatile_get_is_empty()}
 * @property-read boolean $is_forbidden {@link volatile_get_is_forbidden()}
 * @property-read boolean $is_fresh {@link volatile_get_is_fresh()}
 * @property-read boolean $is_informational {@link volatile_get_is_informational()}
 * @property-read boolean $is_not_found {@link volatile_get_is_not_found()}
 * @property-read boolean $is_ok {@link volatile_get_is_ok()}
 * @property-read boolean $is_private {@link volatile_get_is_private()}
 * @property-read boolean $is_redirection {@link volatile_get_is_redirection()}
 * @property-read boolean $is_server_error {@link volatile_get_is_server_error()}
 * @property-read boolean $is_successful {@link volatile_get_is_successful()}
 * @property-read boolean $is_valid {@link volatile_get_is_valid()}
 * @property-read boolean $is_validateable {@link volatile_get_is_validateable()}
 *
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616.html
 */
class Response extends \ICanBoogie\Object
{
	static public $status_messages = array
	(
		100 => "Continue",
		101 => "Switching Protocols",
		200 => "OK",
		201 => "Created",
		202 => "Accepted",
		203 => "Non-Authoritative Information",
		204 => "No Content",
		205 => "Reset Content",
		206 => "Partial Content",
		300 => "Multiple Choices",
		301 => "Moved Permanently",
		302 => "Found",
		303 => "See Other",
		304 => "Not Modified",
		305 => "Use Proxy",
		307 => "Temporary Redirect",
		400 => "Bad Request",
		401 => "Unauthorized",
		402 => "Payment Required",
		403 => "Forbidden",
		404 => "Not Found",
		405 => "Method Not Allowed",
		406 => "Not Acceptable",
		407 => "Proxy Authentication Required",
		408 => "Request Timeout",
		409 => "Conflict",
		410 => "Gone",
		411 => "Length Required",
		412 => "Precondition Failed",
		413 => "Request Entity Too Large",
		414 => "Request-URI Too Long",
		415 => "Unsupported Media Type",
		416 => "Requested Range Not Satisfiable",
		417 => "Expectation Failed",
		418 => "I'm a teapot",
		500 => "Internal Server Error",
		501 => "Not Implemented",
		502 => "Bad Gateway",
		503 => "Service Unavailable",
		504 => "Gateway Timeout",
		505 => "HTTP Version Not Supported"
	);

	/**
	 * Response headers.
	 *
	 * @var Headers
	 */
	public $headers;

	/**
	 * @var string The HTTP protocol version (1.0 or 1.1), defaults to '1.0'
	 */
	public $version = '1.0';

	/**
	 * Initializes the {@link $header}, {@link $date}, {@link $status} and {@link $body}
	 * properties.
	 *
	 * @param int $status The status code of the response.
	 * @param array $headers The initial header fields of the response.
	 * @param mixed $body The body of the response.
	 */
	public function __construct($status=200, array $headers=array(), $body=null)
	{
		$this->headers = new Headers($headers);

		if (!$this->headers['Date'])
		{
			$this->date = 'now';
		}

		$this->volatile_set_status($status);
		$this->volatile_set_body($body);
	}

	/**
	 * The header is cloned when the response is cloned.
	 */
	public function __clone()
	{
		$this->headers = clone $this->headers;
	}

	/**
	 * Renders the response as an HTTP string.
	 *
	 * @return string
	 */
	public function __toString()
	{
		$body = $this->body;

		if (is_callable($body))
		{
			ob_start();

			$body();

			$body = ob_get_clean();
		}
		else
		{
			$body = (string) $body;
		}

		return "HTTP/{$this->version} {$this->status} {$this->status_message}"
		. $this->headers
		. "\r\n"
		. $body;
	}

	/**
	 * Issues the HTTP response.
	 *
	 * The header is modified according to the {@link version}, {@link status} and
	 * {@link status_message} properties.
	 *
	 * The usual behaviour of the response is to echo its body and then terminate the script. But
	 * if its body is `null` the following happens :
	 *
	 * - If the {@link $location} property is defined the script is terminated.
	 *
	 * - If the {@link $is_ok} property is falsy **the method returns**.
	 *
	 * Note: If the body is a `callable`, the provided callable MUST echo the response body.
	 */
	public function __invoke()
	{
		if (headers_sent($file, $line))
		{
			trigger_error(\ICanBoogie\format
			(
				"Cannot modify header information because it was already sent. Output started at !at.", array
				(
					'at' => $file . ':' . $line
				)
			));
		}
		else
		{
			header_remove('Pragma');
			header_remove('X-Powered-By');

			header("HTTP/{$this->version} {$this->status} {$this->status_message}");

			$this->headers();
		}

		$body = $this->body;

		if ($body === null)
		{
			if ($this->location)
			{
				exit;
			}
			else if (!$this->is_ok)
			{
				return;
			}
		}

		if (is_callable($body))
		{
			$body();
		}
		else
		{
			echo $body;
		}

		exit;
	}

	/**
	 * Status of the HTTP response.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Message describing the status code.
	 *
	 * @var string
	 */
	public $status_message;

	/**
	 * Sets response status code and optionally status message.
	 *
	 * This method is the setter for the {@link $status} property.
	 *
	 * @param integer|array $status HTTP status code or HTTP status code and HTTP status message.
	 *
	 * @throws \InvalidArgumentException When the HTTP status code is not valid.
	 */
	protected function volatile_set_status($status)
	{
		$status_message = null;

		if (is_array($status))
		{
			list($status, $status_message) = $status;
		}

		$this->status = (int) $status;

		if (!$this->is_valid)
		{
			throw new \InvalidArgumentException(\ICanBoogie\format
			(
				'The HTTP status code %status is not valid.', array('%status' => $status)
			));
		}

		if ($status_message === null)
		{
			unset($this->status_message);
		}
		else
		{
			$this->status_message = $status_message;
		}
	}

	/**
	 * Returns the response status code.
	 *
	 * This method is the getter for the {@link $status} property.
	 *
	 * @return integer
	 */
	protected function volatile_get_status()
	{
		return $this->status;
	}

	/**
	 * The response body.
	 *
	 * @var mixed
	 *
	 * @see volatile_set_body(), volatile_get_body()
	 */
	private $body;

	/**
	 * Sets the response body.
	 *
	 * The body can be any data type that can be converted into a string this includes numeric and
	 * objects implementing the {@link __toString()} method.
	 *
	 * Note: This method is the setter for the {@link $body} property.
	 *
	 * @param string|int|object|callable $body
	 *
	 * @throws \UnexpectedValueException when the body cannot be converted to a string.
	 */
	protected function volatile_set_body($body)
	{
		if ($body !== null && !is_string($body) && !is_numeric($body) && !is_callable(array($body, '__toString')) && !is_callable($body))
		{
			throw new \UnexpectedValueException(\ICanBoogie\format
			(
				'The Response body must be a string, an object implementing the __toString() method or be callable, %type given. !value', array
				(
					'type' => gettype($body),
					'value' => $body
				)
			));
		}

		$this->body = $body;
	}

	/**
	 * Returns the response body.
	 *
	 * Note: This method is the getter for the {@link $body} property.
	 *
	 * @return string
	 */
	protected function volatile_get_body()
	{
		return $this->body;
	}

	/**
	 * Returns the message associated with the status code.
	 *
	 * This method is the volatile getter for the {@link $status_message} property and is only
	 * called if the property is not accessible.
	 *
	 * @return string
	 */
	protected function volatile_get_status_message()
	{
		return self::$status_messages[$this->status];
	}

	/**
	 * Sets the value of the `Location` header field.
	 *
	 * @param string $url
	 */
	protected function volatile_set_location($url)
	{
		$this->headers['Location'] = $url;
	}

	/**
	 * Returns the value of the `Location` header field.
	 *
	 * @return string
	 */
	protected function volatile_get_location()
	{
		return $this->headers['Location'];
	}

	/**
	 * Sets the value of the `Content-Type` header field.
	 *
	 * @param string $content_type
	 */
	protected function volatile_set_content_type($content_type)
	{
		$this->headers['Content-Type'] = $content_type;
	}

	/**
	 * Returns the value of the `Content-Type` header field.
	 *
	 * @return string
	 */
	protected function volatile_get_content_type()
	{
		return $this->headers['Content-Type'];
	}

	/**
	 * Sets the value of the `Content-Length` header field.
	 *
	 * @param int $length
	 */
	protected function volatile_set_content_length($length)
	{
		$this->headers['Content-Length'] = $length;
	}

	/**
	 * Returns the value of the `Content-Length` header field.
	 *
	 * @return int
	 */
	protected function volatile_get_content_length()
	{
		return $this->headers['Content-Length'];
	}

	/**
	 * Sets the value of the `Date` header field.
	 *
	 * @param mixed $time If 'now' is passed a {@link \Datetime} object is created with the UTC
	 * time zone.
	 */
	protected function volatile_set_date($time)
	{
		if ($time == 'now')
		{
			$time = new \DateTime(null, new \DateTimeZone('UTC'));
		}

		$this->headers['Date'] = $time;
	}

	/**
	 * Returns the value of the `Date` header field.
	 *
	 * @return string
	 */
	protected function volatile_get_date()
	{
		return $this->headers['Date'];
	}

	/**
	 * Sets the value of the `Age` header field.
	 *
	 * @param int $age
	 */
	protected function volatile_set_age($age)
	{
		$this->headers['Age'] = $age;
	}

	/**
	 * Returns the age of the response.
	 *
	 * @return int
	 */
	protected function volatile_get_age()
	{
		$age = $this->headers['Age'];

		if ($age)
		{
			return $age;
		}

		return max(time() - $this->date->format('U'), 0);
	}

	/**
	 * Sets the value of the `Last-Modified` header field.
	 *
	 * @param mixed $time.
	 */
	protected function volatile_set_last_modified($time)
	{
		$this->headers['Last-Modified'] = $time;
	}

	/**
	 * Returns the value of the `Last-Modified` header field.
	 *
	 * @return string
	 */
	protected function volatile_get_last_modified()
	{
		return $this->headers['Last-Modified'];
	}

	/**
	 * Sets the value of the `Expires` header field.
	 *
	 * The method also calls the {@link session_cache_expire()} function.
	 *
	 * @param mixed $time.
	 */
	protected function volatile_set_expires($time)
	{
		$this->headers['Expires'] = $time;

		session_cache_expire($time); // TODO-20120831: Is this required now that we have an awesome response system ?
	}

	/**
	 * Returns the value of the `Expires` header field.
	 *
	 * @return string
	 */
	protected function volatile_get_expires()
	{
		return $this->headers['Expires'];
	}

	/**
	 * Sets the value of the `Etag` header field.
	 *
	 * @param string $value
	 */
	protected function volatile_set_etag($value)
	{
		$this->headers['Etag'] = $value;
	}

	/**
	 * Returns the value of the `Etag` header field.
	 *
	 * @return string
	 */
	protected function volatile_get_etag()
	{
		return $this->headers['Etag'];
	}

	/**
	 * Sets the directives of the `Cache-Control` header field.
	 *
	 * @param string $cache_directives
	 */
	protected function volatile_set_cache_control($cache_directives)
	{
		$this->headers['Cache-Control'] = $cache_directives;
	}

	/**
	 * Returns the `Cache-Control` header field.
	 *
	 * @return \ICanBoogie\HTTP\Headers\CacheControl
	 */
	protected function volatile_get_cache_control()
	{
		return $this->headers['Cache-Control'];
	}

	/**
	 * Sets the response's time-to-live for shared caches.
	 *
	 * This method adjusts the Cache-Control/s-maxage directive.
	 *
	 * @param int $seconds The number of seconds.
	 */
	protected function volatile_set_ttl($seconds)
	{
		$this->cache_control->s_max_age = $this->age->timestamp + $seconds;
	}

	/**
	 * Returns the response's time-to-live in seconds.
	 *
	 * When the responses TTL is <= 0, the response may not be served from cache without first
	 * revalidating with the origin.
	 *
	 * @return int|null The number of seconds to live, or `null` is no freshness information
	 * is present.
	 */
	protected function volatile_get_ttl()
	{
		$max_age = $this->cache_control->max_age;

		if ($max_age)
		{
			return $max_age - $this->age;
		}
	}

	/**
	 * Set the `cacheable` property of the `Cache-Control` header field to `private`.
	 *
	 * @param boolean $value
	 */
	protected function volatile_set_is_private($value)
	{
		$this->cache_control->cacheable = 'private';
	}

	/**
	 * Checks that the `cacheable` property of the `Cache-Control` header field is `private`.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_private()
	{
		return $this->cache_control->cacheable == 'private';
	}

	/**
	 * Checks if the response is valid.
	 *
	 * A response is considered valid when its status is between 100 and 600, 100 included.
	 *
	 * Note: This method is the getter for the `is_valid` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_valid()
	{
		return $this->status >= 100 && $this->status < 600;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_valid}.
	 */
	protected function volatile_set_is_valid()
	{
		throw new Exception\PropertyNotWritable(array('is_valid', $this));
	}

	/**
	 * Checks if the response is informational.
	 *
	 * A response is considered informational when its status is between 100 and 200, 100 included.
	 *
	 * Note: This method is the getter for the `is_informational` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_informational()
	{
		return $this->status >= 100 && $this->status < 200;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to wrtie {@link $is_informational}.
	 */
	protected function volatile_set_is_informational()
	{
		throw new Exception\PropertyNotWritable(array('is_informational', $this));
	}

	/**
	 * Checks if the response is successful.
	 *
	 * A response is considered successful when its status is between 200 and 300, 200 included.
	 *
	 * Note: This method is the getter for the `is_successful` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_successful()
	{
		return $this->status >= 200 && $this->status < 300;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_successful}.
	 */
	protected function volatile_set_is_successful()
	{
		throw new Exception\PropertyNotWritable(array('is_successful', $this));
	}

	/**
	 * Checks if the response is a redirection.
	 *
	 * A response is considered to be a redirection when its status is between 300 and 400, 300
	 * included.
	 *
	 * Note: This method is the getter for the `is_redirection` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_redirection()
	{
		return $this->status >= 300 && $this->status < 400;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_redirection}.
	 */
	protected function volatile_set_is_redirection()
	{
		throw new Exception\PropertyNotWritable(array('is_redirection', $this));
	}

	/**
	 * Checks if the response is a client error.
	 *
	 * A response is considered a client error when its status is between 400 and 500, 400
	 * included.
	 *
	 * Note: This method is the getter for the `is_client_error` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_client_error()
	{
		return $this->status >= 400 && $this->status < 500;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_client_error}.
	 */
	protected function volatile_set_is_client_error()
	{
		throw new Exception\PropertyNotWritable(array('is_client_error', $this));
	}

	/**
	 * Checks if the response is a server error.
	 *
	 * A response is considered a server error when its status is between 500 and 600, 500
	 * included.
	 *
	 * Note: This method is the getter for the `is_server_error` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_server_error()
	{
		return $this->status >= 500 && $this->status < 600;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_server_error}.
	 */
	protected function volatile_set_is_server_error()
	{
		throw new Exception\PropertyNotWritable(array('is_server_error', $this));
	}

	/**
	 * Checks if the response is ok.
	 *
	 * A response is considered ok when its status is 200.
	 *
	 * Note: This method is the getter for the `is_ok` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_ok()
	{
		return $this->status == 200;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_ok}.
	 */
	protected function volatile_set_is_ok()
	{
		throw new Exception\PropertyNotWritable(array('is_ok', $this));
	}

	/**
	 * Checks if the response is forbidden.
	 *
	 * A response is forbidden ok when its status is 403.
	 *
	 * Note: This method is the getter for the `is_forbidden` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_forbidden()
	{
		return $this->status == 403;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to wrtie {@link $is_forbidden}.
	 */
	protected function volatile_set_is_forbidden()
	{
		throw new Exception\PropertyNotWritable(array('is_forbidden', $this));
	}

	/**
	 * Checks if the response is not found.
	 *
	 * A response is considered not found when its status is 404.
	 *
	 * Note: This method is the getter for the `is_not_found` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_not_found()
	{
		return $this->status == 404;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_not_found}.
	 */
	protected function volatile_set_is_not_found()
	{
		throw new Exception\PropertyNotWritable(array('is_not_found', $this));
	}

	/**
	 * Checks if the response is empty.
	 *
	 * A response is considered empty when its status is 201, 204 or 304.
	 *
	 * Note: This method is the getter for the `is_empty` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_empty()
	{
		return in_array($this->status, array(201, 204, 304));
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link is_empty}.
	 */
	protected function volatile_set_is_empty()
	{
		throw new Exception\PropertyNotWritable(array('is_empty', $this));
	}

	/**
	 * Checks that the response includes header fields that can be used to validate the response
	 * with the origin server using a conditional GET request.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_validateable()
	{
		return $this->headers['Last-Modified'] || $this->headers['ETag'];
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_validateable}.
	 */
	protected function volatile_set_is_validateable()
	{
		throw new Exception\PropertyNotWritable(array('is_validateable', $this));
	}

	/**
	 * Checks that the response is worth caching under any circumstance.
	 *
	 * Responses marked _private_ with an explicit `Cache-Control` directive are considered
	 * uncacheable.
	 *
	 * Responses with neither a freshness lifetime (Expires, max-age) nor cache validator
	 * (`Last-Modified`, `ETag`) are considered uncacheable.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_cacheable()
	{
		if (!in_array($this->status, array(200, 203, 300, 301, 302, 404, 410)))
		{
			return false;
		}

		if ($this->cache_control->no_store || $this->cache_control->cacheable == 'private')
		{
			return false;
		}

		return $this->is_validateable() || $this->is_fresh();
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_cacheable}.
	 */
	protected function volatile_set_is_cacheable()
	{
		throw new Exception\PropertyNotWritable(array('is_cacheable', $this));
	}

	/**
	 * Checks if the response is fresh.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_fresh()
	{
		return $this->ttl > 0;
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write {@link $is_fresh}.
	 */
	protected function volatile_set_is_fresh()
	{
		throw new Exception\PropertyNotWritable(array('is_fresh', $this));
	}

	/**
	 * @throws Exception\PropertyNotWritable in attempt to write an unsupported property.
	 */
	protected function last_chance_set($property, $value, &$success)
	{
		throw new Exception\PropertyNotWritable(array($property, $this));
	}
}