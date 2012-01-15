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

/**
 * @property string $body {@link __volatile_set_body()} {@link __volatile_get_body()}
 * @property integer $date {@link __volatile_set_date()} {@link __volatile_get_date()}
 * @property integer $expires {@link __volatile_set_expires()} {@link __volatile_get_expires()}
 * @property integer $status {@link __volatile_set_status()} {@link __volatile_get_status()}
 * @property integer $last_modified {@link __volatile_set_last_modified()} {@link __volatile_get_last_modified()}
 * @property-read boolean $is_valid {@link __volatile_get_is_valid()}
 * @property-read boolean $is_informational {@link __volatile_get_is_informational()}
 * @property-read boolean $is_successful {@link __volatile_get_is_successful()}
 * @property-read boolean $is_redirection {@link __volatile_get_is_redirection()}
 * @property-read boolean $is_client_error {@link __volatile_get_is_client_error()}
 * @property-read boolean $is_server_error {@link __volatile_get_is_server_error()}
 * @property-read boolean $is_ok {@link __volatile_get_is_ok()}
 * @property-read boolean $is_forbidden {@link __volatile_get_is_forbidden()}
 * @property-read boolean $is_not_found {@link __volatile_get_is_not_found()}
 * @property-read boolean $is_empty {@link __volatile_get_is_empty()}
 *
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616.html
 */
class Response extends \ICanBoogie\Object
{
	protected $body;
	protected $status;
	public $status_message;
	protected $content_type;

	/**
	 * @var string Response charset
	 */
	public $charset;
	public $headers;

	/**
     * @var string The HTTP protocol version (1.0 or 1.1), defaults to '1.0'
     */
	public $version='1.0';

	static public $status_messages = array
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
		$this->__volatile_set_status($status);

		$this->headers = new Headers($headers);

		$this->__volatile_set_body($body);
	}

	public function __invoke()
	{
		header("HTTP/{$this->version} {$this->status} {$this->status_message}");

		foreach ($this->headers as $identifier => $value)
		{
			header("$identifier: $value");
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
     * Sets response status code and optionnaly status message.
     *
     * This method is the setter for the {@link $status} property.
     *
     * @param integer|array $status HTTP status code or HTTP status code and HTTP status message.
     *
     * @throws \InvalidArgumentException When the HTTP status code is not valid.
     */
	protected function __volatile_set_status($status)
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
	protected function __volatile_get_status()
	{
		return $this->status;
	}

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
	protected function __volatile_set_body($body)
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
	protected function __volatile_get_body()
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
	protected function __volatile_get_status_message()
	{
		return self::$status_messages[$this->status];
	}

	/**
	 * Sets the response location.
	 *
	 * This method is a setter for the 'Location' header.
	 *
	 * @param string $url
	 */
	protected function __volatile_set_location($url)
	{
		$this->headers['Location'] = $url;
	}

	protected function __volatile_get_location()
	{
		return $this->headers['Location'];
	}

	protected function __volatile_set_content_type($content_type)
	{
		$this->content_type = $content_type;

		$charset = $this->charset;

		if (!$charset && in_array($content_type, array('text/plain', 'text/html')))
		{
			$charset = 'utf-8';
		}

		if ($content_type && $charset)
		{
			$content_type .= '; charset=' . $charset;
		}

		$this->headers['Content-Type'] = $content_type;
	}

	protected function __volatile_get_content_type()
	{
		return $this->headers['Content-Type'];
	}

	/**
	 * Sets the `Content-Length` header.
	 *
	 * @param int $length
	 */
	protected function __volatile_set_content_length($length)
	{
		$this->headers['Content-Length'] = $length;
	}

	/**
	 * Returns the `Content-Length` header.
	 *
	 * @return int|null The value of the `Content-Length` header or null if it is not defined.
	 */
	protected function __volatile_get_content_length()
	{
		return $this->headers['Content-Length'];
	}

	/**
	 * Sets the `Date` header.
	 *
	 * @param mixed $time.
	 */
	protected function __volatile_set_date($time)
	{
		$this->headers['Date'] = $time;
	}

	/**
	 * Returns the `Date` header.
	 *
	 * @return string|null The value of the `Date` header or null if it is not defined.
	 */
	protected function __volatile_get_date()
	{
		return $this->headers['Date'];
	}

	/**
	 * Sets the `Last-Modified` header.
	 *
	 * @param mixed $time.
	 */
	protected function __volatile_set_last_modified($time)
	{
		$this->headers['Last-Modified'] = $time;
	}

	/**
	 * Returns the `Last-Modified` header.
	 *
	 * @return string|null The value of the `Last-Modified` header or null if it is not defined.
	 */
	protected function __volatile_get_last_modified()
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
	protected function __volatile_set_expires($time)
	{
		$this->headers['Expires'] = $time;

		session_cache_expire($time);
	}

	/**
	 * Returns the `Expires` header.
	 *
	 * @return string|null The value of the `Expires` header or null if it is not defined.
	 */
	protected function __volatile_get_expires()
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
	protected function __volatile_get_is_valid()
	{
		return $this->status >= 100 && $this->status < 600;
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
	protected function __volatile_get_is_informational()
	{
		return $this->status >= 100 && $this->status < 200;
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
	protected function __volatile_get_is_successful()
	{
		return $this->status >= 200 && $this->status < 300;
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
	protected function __volatile_get_is_redirection()
	{
		return $this->status >= 300 && $this->status < 400;
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
	protected function __volatile_get_is_client_error()
	{
		return $this->status >= 400 && $this->status < 500;
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
	protected function __volatile_get_is_server_error()
	{
		return $this->status >= 500 && $this->status < 600;
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
	protected function __volatile_get_is_ok()
	{
		return $this->status == 200;
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
	protected function __volatile_get_is_forbidden()
	{
		return $this->status == 403;
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
	protected function __volatile_get_is_not_found()
	{
		return $this->status == 404;
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
	protected function __volatile_get_is_empty()
	{
		return in_array($this->status, array(201, 204, 304));
	}
}
