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

@define('WDEXCEPTION_WITH_LOG', true);

class Exception extends \Exception
{
	public $code;
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
		# the error message is localized and formated
		#

		$message = t($message, $params);

		parent::__construct($message, $code, $previous);
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

	public function getHTTPCode()
	{
		return $this->code;
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

namespace ICanBoogie\Exception;

/**
 * Thrown when there is something wrong with a property.
 *
 * This is the root class for property exception, one should rather use the PropertyNotFound,
 * PropertyNotReadable or PropertyNotWritable subclasses.
 */
class Property extends \RuntimeException
{

}

/**
 * Thrown when a property could not be found.
 *
 * For example, this could be triggered by an index out of bounds while setting an array value, or
 * by an unreadable property while getting the value of an object.
 */
class PropertyNotFound extends Property
{
	public function __construct($message='', $code=0, $previous=null)
	{
		if (is_array($message))
		{
			list($property, $container) = $message + array(1 => null);

			if (is_object($container))
			{
				$message = \ICanBoogie\format
				(
					'Unknown property %property for object of class %class.', array
					(
						'property' => $property,
						'class' => get_class($container)
					)
				);
			}
			else if (is_array($container))
			{
				$message = \ICanBoogie\format
				(
					'Unknown index %property for the array: !array', array
					(
						'property' => $property,
						'array' => $container
					)
				);
			}
			else
			{
				$message = \ICanBoogie\format
				(
					'Unknown property %property.', array
					(
						'property' => $property
					)
				);
			}
		}

		parent::__construct($message, $code, $previous);
	}
}

/**
 * Thrown when a property is not readable.
 *
 * For example, this could be triggered when a private property is read from a public scope.
 */
class PropertyNotReadable extends Property
{
	public function __construct($message='', $code=0, $previous=null)
	{
		if (is_array($message))
		{
			list($property, $container) = $message + array(1 => null);

			if (is_object($container))
			{
				$message = \ICanBoogie\format
				(
					'The property %property for object of class %class is not readable.', array
					(
						'property' => $property,
						'class' => get_class($container)
					)
				);
			}
			else if (is_array($container))
			{
				$message = \ICanBoogie\format
				(
					'The index %property is not readable for the array: !array', array
					(
						'property' => $property,
						'array' => $container
					)
				);
			}
			else
			{
				$message = \ICanBoogie\format
				(
					'The property %property is not readable.', array
					(
						'property' => $property
					)
				);
			}
		}

		parent::__construct($message, $code, $previous);
	}
}

/**
 * Thrown when a property is not writable.
 *
 * For example, this could be triggered when a private property is written from a public scope.
 */
class PropertyNotWritable extends Property
{
	public function __construct($message='', $code=0, $previous=null)
	{
		if (is_array($message))
		{
			list($property, $container) = $message + array(1 => null);

			if (is_object($container))
			{
				$message = \ICanBoogie\format
				(
					'The property %property for object of class %class is not writable.', array
					(
						'property' => $property,
						'class' => get_class($container)
					)
				);
			}
			else if (is_array($container))
			{
				$message = \ICanBoogie\format
				(
					'The index %property is not writable for the array: !array', array
					(
						'property' => $property,
						'array' => $container
					)
				);
			}
			else
			{
				$message = \ICanBoogie\format
				(
					'The property %property is not writable.', array
					(
						'property' => $property
					)
				);
			}
		}

		parent::__construct($message, $code, $previous);
	}
}

/**
 * Thrown when an offset is not readable.
 *
 * For example, this could be triggered when a value of a readonly ArrayAcces object is read.
 */
class OffsetNotReadable extends Property
{
	public function __construct($message='', $code=0, $previous=null)
	{
		if (is_array($message))
		{
			list($offset, $container) = $message + array(1 => null);

			if (is_object($container))
			{
				$message = \ICanBoogie\format
				(
					'The offset %offset for object of class %class is not readable.', array
					(
						'offset' => $offset,
						'class' => get_class($container)
					)
				);
			}
			else if (is_array($container))
			{
				$message = \ICanBoogie\format
				(
					'The offset %offset is not readable for the array: !array', array
					(
						'offset' => $offset,
						'array' => $container
					)
				);
			}
			else
			{
				$message = \ICanBoogie\format
				(
					'The offset %offset is not readable.', array
					(
						'offset' => $offset
					)
				);
			}
		}

		parent::__construct($message, $code, $previous);
	}
}

/**
 * Thrown when an offset is not writable.
 *
 * For example, this could be triggered when a value of a readonly ArrayAcces object is set.
 */
class OffsetNotWritable extends Property
{
	public function __construct($message='', $code=0, $previous=null)
	{
		if (is_array($message))
		{
			list($offset, $container) = $message + array(1 => null);

			if (is_object($container))
			{
				$message = \ICanBoogie\format
				(
					'The offset %offset for object of class %class is not writable.', array
					(
						'offset' => $offset,
						'class' => get_class($container)
					)
				);
			}
			else if (is_array($container))
			{
				$message = \ICanBoogie\format
				(
					'The offset %offset is not writable for the array: !array', array
					(
						'offset' => $offset,
						'array' => $container
					)
				);
			}
			else
			{
				$message = \ICanBoogie\format
				(
					'The offset %offset is not writable.', array
					(
						'offset' => $offset
					)
				);
			}
		}

		parent::__construct($message, $code, $previous);
	}
}

class HTTP extends \ICanBoogie\Exception
{
	public function __toString()
	{
		if ($this->code && !headers_sent())
		{
			header('HTTP/1.0 ' . $this->code . ' ' . $this->title);
		}

		$rc  = '<code class="exception">';
		$rc .= '<strong>' . $this->title . ', with the following message:</strong><br /><br />';
		$rc .= $this->getMessage() . '<br />';
		$rc .= '</code>';

		return $rc;
	}
}