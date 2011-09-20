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

		#
		#
		#

		$file = $this->getFile();
		$line = $this->getLine();

		$lines = array();

		$lines[] = '<strong>' . $this->title . ', with the following message:</strong><br />';
		$lines[] = $this->getMessage();

		Debug::lineNumber($file, $line, $lines);
		Debug::format_trace($this->getTrace(), $lines);

		#
		# if WDEXCEPTION_WITH_LOG is set to true, we join the messages from the log
		# to the trace
		#

		if (WDEXCEPTION_WITH_LOG)
		{
			$log = array_merge(Debug::fetch_messages('debug'), Debug::fetch_messages('error'), Debug::fetch_messages('done'));

			if ($log)
			{
				$lines[] = '<br /><strong>Log:</strong><br />';

				foreach ($log as $message)
				{
					$lines[] = $message . '<br />';
				}
			}
		}

		#
		# now we join all of these lines, report the message and return it
		# so it can be displayed by the exception handler
		#

		$rc = '<code class="exception">' . implode('<br />' . PHP_EOL, $lines) . '</code>';

		Debug::report($rc);

		return $rc;
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