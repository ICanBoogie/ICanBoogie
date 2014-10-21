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

class Debug
{
	const MODE_DEV = 'dev';
	const MODE_TEST = 'test';
	const MODE_PRODUCTION = 'production';

	static public $mode = 'dev';

	/**
	 * Last error.
	 *
	 * @var array[string]mixed
	 */
	static public $last_error;

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	static public $last_error_message;

	static public function synthesize_config(array $fragments)
	{
		$config = call_user_func_array('ICanBoogie\array_merge_recursive', $fragments);
		$config = array_merge($config, $config['modes'][$config['mode']]);

		return $config;
	}

	static private $config;

	static private $config_code_sample = true;
	static private $config_line_number = true;
	static private $config_report = false;
	static private $config_report_address = null;
	static private $config_stack_trace = true;
	static private $config_exception_chain = true;
	static private $config_verbose = true;

	static public function is_dev()
	{
		return self::$mode == self::MODE_DEV;
	}

	static public function is_test()
	{
		return self::$mode == self::MODE_TEST;
	}

	static public function is_production()
	{
		return self::$mode == self::MODE_PRODUCTION;
	}

	/**
	 * Configures the class.
	 *
	 * @param array $config A config such as one returned by `$core->configs['debug']`.
	 */
	static public function configure(array $config)
	{
		$mode = self::$mode;
		$modes = [];

		foreach ($config as $directive => $value)
		{
			if ($directive == 'mode')
			{
				$mode = $value;

				continue;
			}
			else if ($directive == 'modes')
			{
				$modes = $value;

				continue;
			}

			$directive = 'config_' . $directive;

			self::$$directive = $value;
		}

		self::$mode = $mode;

		if (isset($modes[$mode]))
		{
			foreach ($modes[$mode] as $directive => $value)
			{
				$directive = 'config_' . $directive;

				self::$$directive = $value;
			}
		}
	}

	/**
	 * Stores logged messages in the session and report fatal errors.
	 */
	static public function shutdown_handler()
	{
		$error = error_get_last();

		if ($error && $error['type'] == E_ERROR)
		{
			$message = self::format_alert($error);

			self::report($message);
		}
	}

	/*
	**

	DEBUG & TRACE

	**
	*/

	/**
	 * Handles errors.
	 *
	 * The {@link $last_error} and {@link $last_error_message} properties are updated.
	 *
	 * The alert is formatted, reported and if the `verbose` option is true the alert is displayed.
	 *
	 * @param int $no The level of the error raised.
	 * @param string $str The error message.
	 * @param string $file The filename that the error was raised in.
	 * @param int $line The line number the error was raised at.
	 * @param array $context The active symbol table at the point the error occurred.
	 */
	static public function error_handler($no, $str, $file, $line, $context)
	{
		if (!headers_sent())
		{
			header('HTTP/1.0 500 ' . strip_tags($str));
		}

		$trace = debug_backtrace();

		array_shift($trace); // remove the trace of our function

		$error = [

			'type' => $no,
			'message' => $str,
			'file' => $file,
			'line' => $line,
			'context' => $context,
			'trace' => $trace

		];

		self::$last_error = $error;
		self::$last_error_message = $str;

		$message = self::format_alert($error);

		self::report($message);

		if (self::$config_verbose)
		{
			echo $message;

			flush();
		}
	}

	/**
	 * Basic exception handler.
	 *
	 * @param \Exception $exception
	 */
	static public function exception_handler(\Exception $exception)
	{
		if (!headers_sent())
		{
			$code = $exception->getCode();

			$message = $exception->getMessage();
			$message = strip_tags($message);
			$message = str_replace([ "\r\n", "\n" ], '', $message);

			header("HTTP/1.0 $code $message");
			header("Content-Type: text/html; charset=utf-8");
		}

		$message = self::format_alert($exception);

		self::report($message);

		exit($message);
	}

	const MAX_STRING_LEN = 16;

	static private $error_names = [

		E_ERROR => 'Error',
		E_WARNING => 'Warning',
		E_PARSE => 'Parse error',
		E_NOTICE => 'Notice'

	];

	/**
	 * Formats an alert into a HTML element.
	 *
	 * An alert can be an exception or an array representing an error triggered with the
	 * trigger_error() function.
	 *
	 * @param \Exception|array $alert
	 *
	 * @return string
	 */
	static public function format_alert($alert)
	{
		$type = 'Error';
		$class = 'error';
		$file = null;
		$line = null;
		$message = null;
		$trace = null;
		$more = null;

		if (is_array($alert))
		{
			$file = $alert['file'];
			$line = $alert['line'];
			$message = $alert['message'];

			if (isset(self::$error_names[$alert['type']]))
			{
				$type = self::$error_names[$alert['type']];
			}

			if (isset($alert['trace']))
			{
				$trace = $alert['trace'];
			}
		}
		else if ($alert instanceof \Exception)
		{
			$type = get_class($alert);
			$class = 'exception';
			$file = $alert->getFile();
			$line = $alert->getLine();
			$message = $alert->getMessage();
			$trace = $alert->getTrace();
		}

		$message = strip_tags($message, '<a><em><q><strong>');

		if ($trace)
		{
			$more .= self::format_trace($trace);
		}

		if (is_array($alert) && $file)
		{
			$more .= self::format_code_sample($file, $line);
		}

		$file = strip_root($file);

		$previous = null;

		if (/*self::$config_exception_chain &&*/ $alert instanceof \Exception)
		{
			$previous = $alert->getPrevious();

			if ($previous)
			{
				$previous = self::format_alert($previous);
			}
		}

		return <<<EOT
<pre class="alert alert-error $class">
<strong>$type with the following message:</strong>

$message

in <em>$file</em> at line <em>$line</em>$more{$previous}
</pre>
EOT;
	}

	/**
	 * Formats a stack trace into an HTML element.
	 *
	 * @param array $trace
	 *
	 * @return string
	 */
	static public function format_trace(array $trace)
	{
		$root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
		$count = count($trace);
		$count_max = strlen((string) $count);

		$rc = "\n\n<strong>Stack trace:</strong>\n";

		foreach ($trace as $i => $node)
		{
			$trace_file = null;
			$trace_line = 0;
			$trace_class = null;
			$trace_type = null;
			$trace_args = null;

			extract($node, EXTR_PREFIX_ALL, 'trace');

			if ($trace_file)
			{
				$trace_file = str_replace('\\', '/', $trace_file);
				$trace_file = str_replace($root, '', $trace_file);
			}

			$params = [];

			if ($trace_args)
			{
				foreach ($trace_args as $arg)
				{
					switch (gettype($arg))
					{
						case 'array': $arg = 'Array'; break;
						case 'object': $arg = 'Object of ' . get_class($arg); break;
						case 'resource': $arg = 'Resource of type ' . get_resource_type($arg); break;
						case 'null': $arg = 'null'; break;

						default:
						{
							if (strlen($arg) > self::MAX_STRING_LEN)
							{
								$arg = substr($arg, 0, self::MAX_STRING_LEN) . '...';
							}

							$arg = '\'' . $arg .'\'';
						}
						break;
					}

					$params[] = $arg;
				}
			}

			$rc .= sprintf
			(
				"\n%{$count_max}d. %s(%d): %s%s%s(%s)",

				$count - $i, $trace_file, $trace_line, $trace_class, $trace_type,
				$trace_function, escape(implode(', ', $params))
			);
		}

		return $rc;
	}

	/**
	 * Extracts and formats a code sample around the line that triggered the alert.
	 *
	 * @param string $file
	 * @param int $line
	 *
	 * @return string
	 */
	static public function format_code_sample($file, $line=0)
	{
		$sample = '';
		$fh = new \SplFileObject($file);
		$lines = new \LimitIterator($fh, $line < 5 ? 0 : $line - 5, 10);

		foreach ($lines as $i => $str)
		{
			$i++;

			$str = escape(rtrim($str));

			if ($i == $line)
			{
				$str = '<ins>' . $str . '</ins>';
			}

			$str = str_replace("\t", "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0", $str);
			$sample .= sprintf("\n%6d. %s", $i, $str);
		}

		return "\n\n<strong>Code sample:</strong>\n$sample";
	}

	/**
	 * Reports the alert to the admin of the website.
	 *
	 * The method sends an email to the admin of the website defined whose email address is defined
	 * in the debug config using the "report_address" key.
	 *
	 * @param string $message
	 */
	static public function report($message)
	{
		$report_address = self::$config_report_address;

		if (!$report_address)
		{
			return;
		}

		$more = "\n\n<strong>Request URI:</strong>\n\n" . escape($_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);

		if (!empty($_SERVER['HTTP_REFERER']))
		{
			$more .= "\n\n<strong>Referer:</strong>\n\n" . escape($_SERVER['HTTP_REFERER']);
		}

		if (!empty($_SERVER['HTTP_USER_AGENT']))
		{
			$more .= "\n\n<strong>User Agent:</strong>\n\n" . escape($_SERVER['HTTP_USER_AGENT']);
		}

		$more .= "\n\n<strong>Remote address:</strong>\n\n" . escape($_SERVER['REMOTE_ADDR']);

		if ($message instanceof \Exception)
		{
			$message = self::format_alert($message);
		}

		$message = str_replace('</pre>', '', $message);
		$message = trim($message) . $more . '</pre>';

		#
		# during the same session, same messages are only reported once
		#

		$hash = md5($message);

		if (isset($_SESSION['wddebug']['reported'][$hash]))
		{
			return;
		}

		$_SESSION['wddebug']['reported'][$hash] = true;

		#
		#
		#

		$host = $_SERVER['SERVER_NAME'];
		$host = str_replace('www.', '', $host);

		$parts = [

			'From' => 'icanboogie@' . $host,
			'Content-Type' => 'text/html; charset=' . CHARSET

		];

		$headers = '';

		foreach ($parts as $key => $value)
		{
			$headers .= $key .= ': ' . $value . "\r\n";
		}

		mail($report_address, __CLASS__ . ': Report from ' . $host, $message, $headers);
	}

	static private function get_logger()
	{
		return app()->logger;
	}

	/**
	 * The method is forwarded to the core's logger `get_messages()` method.
	 *
	 * @return string[]
	 */
	static public function get_messages($type)
	{
		return self::get_logger()->get_messages($level);
	}

	/**
	 * The method is forwarded to the core's logger `fetch_messages()` method.
	 *
	 * @return string[]
	 */
	static public function fetch_messages($level)
	{
		return self::get_logger()->fetch_messages($level);
	}

	/**
	 * Removes the DOCUMENT_ROOT part from the provided path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	static private function strip_root($path)
	{
		$root = DOCUMENT_ROOT;

		if (strpos($path, $root) === 0)
		{
			return substr($path, strlen($root) - 1);
		}

		return $path;
	}
}