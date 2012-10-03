<?php

namespace ICanBoogie\HTTP;

/**
 * Exception thrown when the HTTP method is not supported.
 */
class MethodNotSupported extends \ICanBoogie\Exception\HTTP
{
	public function __construct($method, $code=500, \Exception $previous=null)
	{
		parent::__construct(\ICanboogie\format('Method not supported: %method', array('method' => $method)), $code, $previous);
	}
}

/**
 * Exception thrown when the HTTP status code is not valid.
 */
class StatusCodeNotValid extends \InvalidArgumentException
{
	public function __construct($status_code, $code=500, \Exception $previous=null)
	{
		parent::__construct("Status code not valid: {$status_code}.", $code, $previous);
	}
}