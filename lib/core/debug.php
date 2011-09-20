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

use ICanBoogie;
use ICanBoogie\I18n\Locale;
use ICanBoogie\I18n\Translator;

class Debug
{
	const MAX_MESSAGES = 100;

	static public function synthesize_config(array $fragments)
	{
		$config = call_user_func_array('wd_array_merge_recursive', $fragments);
		$config = array_merge($config, $config['modes'][$config['mode']]);

		return $config;
	}

	static private $config;

	static public function get_config()
	{
		global $core;

		if (self::$config)
		{
			return self::$config;
		}

		self::$config = (isset($core) ? $core->configs['debug'] : require ROOT . 'config/debug.php');

		return self::$config;
	}

	static public function shutdown_handler()
	{
		global $core;

		if (!self::$logs)
		{
			return;
		}

		if (!headers_sent() && isset($core))
		{
			$core->session;
		}

		$_SESSION['wddebug']['messages'] = self::$logs;

		$error = error_get_last();

		if ($error && $error['type'] == 1)
		{
			$message = <<<EOT
<strong>Fatal error with the following message:</strong><br />
$error[message].<br />
in <em>$error[file]</em> at line <em>$error[line]</em><br />
EOT;

			self::report($message);
		}
	}

	public static function restore_logs(Event $event, ICanBoogie\Session $session)
	{
		if (isset($session->wddebug['messages']))
		{
			self::$logs = array_merge($session->wddebug['messages'], self::$logs);
		}
	}

	/*
	**

	DEBUG & TRACE

	**
	*/

	static public function error_handler($no, $str, $file, $line, $context)
	{
		if (!headers_sent())
		{
			header('HTTP/1.0 500 Error with the following message: ' . strip_tags($str));
		}

		#
		# remove errorHandler trace & trigger trace
		#

		$stack = debug_backtrace();

		array_shift($stack);
		array_shift($stack);

		$config = self::get_config();
		$more = '';

		if ($config['stackTrace'])
		{
			$m = self::format_trace($stack);
			$more .= "\n" . $m;
		}

		if ($config['codeSample'])
		{
			$m = self::format_line($file, $line);
			$more .= "\n" . $m;
		}

		if ($more)
		{
			$more = "\n" . $more;
		}

		$rc = <<<EOT
<pre class="alert-message error debug">
<strong>Error with the following message:</strong>

$str

→ in <em>$file</em> at line <em>$line</em>$more
</pre>
EOT;

		self::report($rc);

		if ($config['verbose'])
		{
			echo $rc;

			flush();
		}
	}

	/**
	 * Minimal exception handler.
	 *
	 * @param \Exception $exception
	 */
	static public function exception_handler(\Exception $exception)
	{
		if (!headers_sent())
		{
			header('HTTP/1.0 500 ' . get_class($exception) . ' with the following message: ' . strip_tags($exception->getMessage()));
		}

		exit(self::format_exception($exception));
	}

	static public function trigger($message, array $args=array())
	{
		$stack = debug_backtrace();
		$caller = array_shift($stack);

		#
		# we skip user_func calls, and get to the real call
		#

		while (empty($caller['file']))
		{
			$caller = array_shift($stack);
		}

		#
		# prolog
		#

		$message = I18n\Translator::format($message, $args);

		$file = $caller['file'];
		$line = $caller['line'];

		$lines = array
		(
			'<strong>Backtrace with the following message:</strong><br />',
			$message . '<br />',
			'in <em>' . $file . '</em> at line <em>' . $line . '</em><br />'
		);

		#
		# stack
		#

		$lines = array_merge($lines, self::format_trace($stack));

		#
		#
		#

		$rc = '<pre class="wd-core-debug"><code>' . implode("<br />\n", $lines) . '</code></pre><br />';

		self::report($rc);

		$config = self::get_config();

		if ($config['verbose'])
		{
			echo '<br /> ' . $rc;
		}
	}

	static public function lineNumber($file, $line, &$saveback=null)
	{
		$lines = array();
		$config = self::get_config();

		if (!$config['lineNumber'])
		{
			return $lines;
		}

		$file = substr($file, strlen(\ICanBoogie\DOCUMENT_ROOT));

		$lines[] = '<br />→ in <em>' . $file . '</em> at line <em>' . $line . '</em>';

		if (is_array($saveback))
		{
			$saveback = array_merge($saveback, $lines);
		}

		return $lines;
	}

	const MAX_STRING_LEN = 16;

	/**
	 * Formats a stack trace into an HTML element.
	 *
	 * @param array $trace
	 *
	 * @return string
	 */
	public static function format_trace(array $trace)
	{
		$root = str_replace('\\', '/', realpath('.'));
		$count = count($trace);
		$count_max = strlen((string) $count);

		$rc = "<strong>Stack trace:</strong>\n";

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

			$params = array();

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
				$trace_function, wd_entities(implode(', ', $params))
			);
		}

		return $rc;
	}

	/**
	 * Formats an exception into a HTML element.
	 *
	 * @param \Exception $exception
	 *
	 * @return string
	 */
	public static function format_exception(\Exception $exception)
	{
		$type = get_class($exception);
		$path = $exception->getFile();
		$line = $exception->getLine();
		$message = $exception->getMessage();
		$trace = self::format_trace($exception->getTrace());

		$path = wd_strip_root($path);

		return <<<EOT
<pre class="alter-message exception">
<strong>$type with the following message:</strong>

$message

→ in <em>$path</em> at line <em>$line</em>

$trace
</pre>
EOT;
	}

	static public function format_line($file, $line=0)
	{
		$config = self::get_config();

		if (!$config['codeSample'])
		{
			return array();
		}

		// TODO-20100718: runtime function have strange filenames.

		if (!file_exists($file))
		{
			return array();
		}

		$sample = '';
		$fh = new \SplFileObject($file);
		$lines = new \LimitIterator($fh, $line < 5 ? 0 : $line - 5, 10);

		foreach ($lines as $i => $str)
		{
			$str = wd_entities(rtrim($str));

			if (++$i == $line)
			{
				$str = '<ins>' . $str . '</ins>';
			}

			$str = str_replace("\t", "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0", $str);
			$sample .= "\n" . $str;
		}

		return <<<EOT

<strong>Code sample:</strong>
$sample
EOT;
	}

	static public function report($message)
	{
		$config = self::get_config();
		$reportAddress = $config['reportAddress'];

		if (!$reportAddress)
		{
			return;
		}

		#
		# add location information
		#

		$message .= '<br /><br /><strong>Request URI:</strong><br /><br />' . wd_entities($_SERVER['REQUEST_URI']);

		if (isset($_SERVER['HTTP_REFERER']))
		{
			$message .= '<br /><br /><strong>Referer:</strong><br /><br />' . wd_entities($_SERVER['HTTP_REFERER']);
		}

		if (isset($_SERVER['HTTP_USER_AGENT']))
		{
			$message .= '<br /><br /><strong>User Agent:</strong><br /><br />' . wd_entities($_SERVER['HTTP_USER_AGENT']);
		}

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

		$host = $_SERVER['HTTP_HOST'];
		$host = str_replace('www.', '', $host);

		$parts = array
		(
			'From' => 'wddebug@' . $host,
			'Content-Type' => 'text/html; charset=' . WDCORE_CHARSET
		);

		$headers = '';

		foreach ($parts as $key => $value)
		{
			$headers .= $key .= ': ' . $value . "\r\n";
		}

		mail($reportAddress, 'ICanBoogie\Debug: Report from ' . $host, $message, $headers);
	}

	static $logs = array();

	static function log($type, $message, array $params=array(), $message_id=null)
	{
		if (empty(self::$logs[$type]))
		{
			self::$logs[$type] = array();
		}

		#
		# limit the number of messages
		#

		$messages = &self::$logs[$type];

		if ($messages)
		{
			$max = self::MAX_MESSAGES;
			$count = count($messages);

			if ($count > $max)
			{
				$messages = array_splice($messages, $count - $max);

				array_unshift($messages, array('*** SLICED', array()));
			}
		}

		$message_id ? $messages[$message_id] = array($message, $params) : $messages[] = array($message, $params);
	}

	/**
	 * Returns the messages available in a given log.
	 *
	 * @param string $type The log type.
	 *
	 * @return array The messages available in the given log.
	 */
	public static function get_messages($type)
	{
		if (empty(self::$logs[$type]))
		{
			return array();
		}

		$rc = array();

		foreach (self::$logs[$type] as $message)
		{
			$rc[] = t($message[0], $message[1]);
		}

		return $rc;
	}

	/**
	 * Similar to the {@link get_message()} method, the method returns the messages available in a
	 * given log, but clear the log after the messages have been extracted.
	 *
	 * @param string $type
	 *
	 * @return array The messages fetched from the given log.
	 */
	public static function fetch_messages($type)
	{
		$rc = self::get_messages($type);

		self::$logs[$type] = array();

		return $rc;
	}

	private function strip_root($str)
	{
		if (strpos($str, DOCUMENT_ROOT) === 0)
		{
			return substr($str, strlen(DOCUMENT_ROOT) - 1);
		}

		return $str;
	}
}

register_shutdown_function('ICanBoogie\Debug::shutdown_handler');