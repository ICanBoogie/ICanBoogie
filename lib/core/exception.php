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
 * @property-read int $code The code of the exception that can be used as HTTP status code.
 * @property-read string $message The message of the exception.
 */
class Exception extends \Exception
{
	protected $code;
	public $title = 'Exception';

	public function __construct($message, array $params=array(), $code=500, $previous=null)
	{
		static $codes = array
		(
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

			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported'
		);

		$this->code = $code;

		if (is_array($code))
		{
			$this->code = key($code);
			$this->title = array_shift($code);
		}
		else if (isset($codes[$code]))
		{
			$this->title = $codes[$code];
		}

		#
		# the error message is localized and formatted
		#

		$message = \ICanBoogie\format($message, $params);

		parent::__construct($message, $code, $previous);
	}

	/**
	 * Returns the read-only properties {@link $code} and {@link $message}.
	 *
	 * @param string $property The property to get.
	 *
	 * @throws PropertyNotReadable When the property is unaccessible.
	 *
	 * @return mixed
	 */
	public function __get($property)
	{
		switch ($property)
		{
			case 'message': return $this->getMessage();
			case 'code': return $this->code;
		}

		throw new PropertyNotReadable(array($property, $this));
	}

	public function __toString()
	{
		if ($this->code && !headers_sent())
		{
			header('HTTP/1.0 ' . $this->code . ' ' . $this->title);
		}

		$message = Debug::format_alert($this);

		Debug::report($message);

		return $message;
	}

	public function getTitle()
	{
		return $this->code . ' ' . $this->title;
	}

	/**
	 * Alters the HTTP header according to the exception code and title.
	 */
	public function alter_header()
	{
		header("HTTP/1.0 $this->code $this->title");
	}
}

/**
 * Exception thrown when a security error occurs.
 *
 * This is a base class for security exceptions, one should rather use the
 * {@link AuthenticationRequired} and {@link PermissionRequired} exceptions.
 */
class SecurityException extends \Exception
{

}

/**
 * Exception thrown when user authentication is required.
 */
class AuthenticationRequired extends SecurityException
{
	public function __construct($message="The requested URL requires authentication.", $code=401, \Exception $previous=null)
	{
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Exception thrown when the user is already authenticated.
 *
 * Third parties may use this exception to redirect authenticated user from a login page to their
 * profile page.
 */
class AlreadyAuthenticated extends SecurityException
{
	public function __construct($message="The user is already authenticated", $code=401, \Exception $previous=null)
	{
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Exception thrown when a user doesn't have the required permission.
 */
class PermissionRequired extends SecurityException
{
	public function __construct($message="You don't have the required permission.", $code=401, \Exception $previous=null)
	{
		parent::__construct($message, $code, $previous);
	}
}