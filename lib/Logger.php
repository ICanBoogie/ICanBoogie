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
 * A message logger using the core's session to store the messages.
 */
class Logger implements LoggerInterface
{
	const MAX_MESSAGES = 50;

	use LoggerTrait;

	static public function get_logger(Core $app)
	{
		static $logger;

		if (!$logger)
		{
			$logger = new static($app);
		}

		return $logger;
	}

	static private function format_messages(array $messages)
	{
		$rc = [];

		foreach ($messages as $message_and_context)
		{
			list($message, $context) = $message_and_context;

			$message = (string) $message;

			if ($context)
			{
				$message = format($message, $context);
			}

			$rc[] = $message;
		}

		return $rc;
	}

	private $app;

	public function __construct(Core $app)
	{
		$this->app = $app;
	}

	public function log($level, $message, array $context = [])
	{
		$messages = &$this->get_stash()[$level];
		$messages[] = [ $message, $context ];

		$count = count($messages);
		$max = self::MAX_MESSAGES;

		if ($count + 1 > $max)
		{
			$messages = array_splice($messages, $count - $max + 1);
			array_unshift($messages, [ '*** SLICED', [] ]);
		}
	}

	public function get_messages($level)
	{
		$messages = &$this->get_stash()[$level];

		if (!$messages)
		{
			return [];
		}

		return self::format_messages($messages);
	}

	public function fetch_messages($level)
	{
		$messages = $this->get_messages($level);

		$this->get_stash()[$level] = [];

		return $messages;
	}

	private function &get_stash()
	{
		$stash = &$this->app->session->icanboogie_logger;

		return $stash;
	}
}
