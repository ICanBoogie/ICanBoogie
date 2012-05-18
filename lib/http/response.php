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
 * @property string $body {@link volatile_set_body()} {@link volatile_get_body()}
 * @property string|array $content_type {@link volatile_set_content_type()} {@link volatile_get_content_type()}
 * @property integer $date {@link volatile_set_date()} {@link volatile_get_date()}
 * @property integer $expires {@link volatile_set_expires()} {@link volatile_get_expires()}
 * @property integer $status {@link volatile_set_status()} {@link volatile_get_status()}
 * @property integer $last_modified {@link volatile_set_last_modified()} {@link volatile_get_last_modified()}
 * @property-read boolean $is_valid {@link volatile_get_is_valid()}
 * @property-read boolean $is_informational {@link volatile_get_is_informational()}
 * @property-read boolean $is_successful {@link volatile_get_is_successful()}
 * @property-read boolean $is_redirection {@link volatile_get_is_redirection()}
 * @property-read boolean $is_client_error {@link volatile_get_is_client_error()}
 * @property-read boolean $is_server_error {@link volatile_get_is_server_error()}
 * @property-read boolean $is_ok {@link volatile_get_is_ok()}
 * @property-read boolean $is_forbidden {@link volatile_get_is_forbidden()}
 * @property-read boolean $is_not_found {@link volatile_get_is_not_found()}
 * @property-read boolean $is_empty {@link volatile_get_is_empty()}
 *
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616.html
 */
class Response extends \ICanBoogie\Object
{
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

	public static $status_messages = array
	(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    );

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
	 * The headers are cloned when the response is cloned.
	 */
	public function __clone()
	{
		$this->headers = clone $this->headers;
	}

	/**
	 * Issues the HTTP response.
	 *
	 * Headers are modified according tp the {@link version}, {@link status} and
	 * {@link status_message} properties. Additionnal headers can be provided by the framework or
	 * the user.
	 *
	 * The usual behaviour of the response is to echo its body then terminate the script. But if
	 * its body is `null` the following happens :
	 *
	 * - If the {@link location} property is defined the script is terminated.
	 *
	 * - If the {@link is_ok} property is falsy **the method returns**.
	 *
	 * Note: If the body is a `callable`, the provided callable must echo the reponse body.
	 */
	public function __invoke()
	{
		if (headers_sent($headers_sent_file, $headers_sent_line))
		{
			trigger_error(\ICanBoogie\format
			(
				"Cannot modify header information because headers were already sent. Output started at !at.", array('at' => $headers_sent_file . ':' . $headers_sent_line)
			));
		}
		else
		{
			header("HTTP/{$this->version} {$this->status} {$this->status_message}");

			foreach ($this->headers as $identifier => $value)
			{
				header("$identifier: $value");
			}
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
	public $status_message;

	/**
     * Sets response status code and optionnaly status message.
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
            throw new \InvalidArgumentException(t('The HTTP status code %status is not valid.', array('%status' => $status)));
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
	 * The body can be any datatype that can be converted into a string this includes numerics and
	 * objects implementing the `__toString()` method.
	 *
	 * Note: This method is the setter for the {@link $body} property.
	 *
	 * @param string|numeric|object|callable $body
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
	 * Sets the `Location` header.
	 *
	 * @param string $url
	 */
	protected function volatile_set_location($url)
	{
		$this->headers['Location'] = $url;
	}

	/**
	 * Returns the `Location` header.
	 *
	 * @return string
	 */
	protected function volatile_get_location()
	{
		return $this->headers['Location'];
	}

	/**
	 * Content type.
	 *
	 * @var string
	 */
	private $content_type;

	/**
	 * Content charset.
	 *
	 * @var string
	 */
	public $charset;

	/**
	 * Sets the `Content-Type` header.
	 *
	 * The value provided is altered if the {@link charset} property is defined. If the property
	 * is empty but the content type is "text/plain" or "text/html" then the charset default to
	 * "utf-8".
	 *
	 * @param string $content_type
	 */
	protected function volatile_set_content_type($content_type)
	{
		$this->content_type = $content_type;

		$charset = $this->charset;

		if (!$charset && in_array($content_type, array('text/plain', 'text/html')))
		{
			$charset = 'utf-8';
		}

		if ($charset)
		{
			$content_type .= '; charset=' . $charset;
		}

		$this->headers['Content-Type'] = $content_type;
	}

	/**
	 * Returns the content type of the response.
	 *
	 * The value is returned for the private {@link content_type} property. If the property is
	 * empty and the `Content-Type`header is defined, the _type_ part of its value is returned.
	 *
	 * @return string
	 */
	protected function volatile_get_content_type()
	{
		$content_type = $this->content_type;

		if (!$content_type && isset($this->headers['Content-Type']))
		{
			list($content_type) = explode(';', $this->headers['Content-Type']);
		}

		return $content_type;
	}

	/**
	 * Sets the `Content-Length` header.
	 *
	 * @param int $length
	 */
	protected function volatile_set_content_length($length)
	{
		$this->headers['Content-Length'] = $length;
	}

	/**
	 * Returns the `Content-Length` header.
	 *
	 * @return int|null The value of the `Content-Length` header or null if it is not defined.
	 */
	protected function volatile_get_content_length()
	{
		return $this->headers['Content-Length'];
	}

	/**
	 * Sets the `Date` header.
	 *
	 * @param mixed $time.
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
	 * Returns the `Date` header.
	 *
	 * @return string|null The value of the `Date` header or null if it is not defined.
	 */
	protected function volatile_get_date()
	{
		return $this->headers['Date'];
	}

	/**
	 * Sets the `Last-Modified` header.
	 *
	 * @param mixed $time.
	 */
	protected function volatile_set_last_modified($time)
	{
		$this->headers['Last-Modified'] = $time;
	}

	/**
	 * Returns the `Last-Modified` header.
	 *
	 * @return string|null The value of the `Last-Modified` header or null if it is not defined.
	 */
	protected function volatile_get_last_modified()
	{
		return $this->headers['Last-Modified'];
	}

	/**
	 * Sets the `Expires` header.
	 *
	 * The method also call the session_cache_expire().
	 *
	 * @param mixed $time.
	 */
	protected function volatile_set_expires($time)
	{
		$this->headers['Expires'] = $time;

		session_cache_expire($time);
	}

	/**
	 * Returns the `Expires` header.
	 *
	 * @return string|null The value of the `Expires` header or null if it is not defined.
	 */
	protected function volatile_get_expires()
	{
		return $this->headers['Expires'];
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

	protected function volatile_set_is_valid()
	{
		throw new Exception\PropertyNotWritable(array('is_valid', $this));
	}

	/**
	 * Checks if the response is informational.
	 *
	 * A response is considered informational when its status is beetween 100 and 200, 100 included.
	 *
	 * Note: This method is the getter for the `is_informational` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_informational()
	{
		return $this->status >= 100 && $this->status < 200;
	}

	protected function volatile_set_is_informational()
	{
		throw new Exception\PropertyNotWritable(array('is_informational', $this));
	}

	/**
	 * Checks if the response is successful.
	 *
	 * A response is considered successful when its status is beetween 200 and 300, 200 included.
	 *
	 * Note: This method is the getter for the `is_successful` magic property.
	 *
	 * @return boolean
	 */
	protected function volatile_get_is_successful()
	{
		return $this->status >= 200 && $this->status < 300;
	}

	protected function volatile_set_is_successful()
	{
		throw new Exception\PropertyNotWritable(array('is_successful', $this));
	}

	/**
	 * Checks if the response is a redirection.
	 *
	 * A response is considered to be a redirection when its status is beetween 300 and 400, 300
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

	protected function volatile_set_is_redirection()
	{
		throw new Exception\PropertyNotWritable(array('is_redirection', $this));
	}

	/**
	 * Checks if the response is a client error.
	 *
	 * A response is considered a client error when its status is beetween 400 and 500, 400
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

	protected function volatile_set_is_client_error()
	{
		throw new Exception\PropertyNotWritable(array('is_client_error', $this));
	}

	/**
	 * Checks if the response is a server error.
	 *
	 * A response is considered a server error when its status is beetween 500 and 600, 500
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

	protected function volatile_set_is_empty()
	{
		throw new Exception\PropertyNotWritable(array('is_empty', $this));
	}

	/*
	 * CACHE
	 *
	 * http://tools.ietf.org/html/rfc2616#section-14.9
	 */

	/**
	 * Marks the response as either public (true) or private (false).
	 *
	 * @var boolean
	 */
	private $cache_control = array
	(
		'public' => true,
		'no-cache' => false,
		'no-store' => false,
		'no-transform' => false,
		'must-revalidate' => false,
		'proxy-revalidate' => false,
		'max-age' => 600
	);

	/**
	 * @return boolean
	 */
	protected function volatile_get_private()
	{
		return !$this->public;
	}

	/**
	 * @param boolean $value
	 */
	protected function volatile_set_private($value)
	{
		$this->public = !$value;
	}

	/**
	 * Checks if the response is fresh.
	 *
	 *
	 *
	 * @return boolean
	 */
    protected function volatile_get_is_fresh()
    {
        return $this->ttl > 0;
    }

    protected function volatile_set_is_fresh()
	{
		throw new Exception\PropertyNotWritable(array('is_fresh', $this));
	}

	protected function volatile_get_is_cacheable()
	{
		if (!in_array($this->status, array(200, 203, 300, 301, 302, 404, 410)))
		{
			return false;
		}

		if ($this->headers->has_cache_control_directive['no-store'] || $this->headers->has_cache_control_directive['private'])
		{
			return false;
		}

		return $this->is_validateable() || $this->is_fresh();
	}
}