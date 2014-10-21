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

	static public function get_logger(Core $core)
	{
		static $logger;

		if (!$logger)
		{
			$logger = new static($core);
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

	private $core;

	public function __construct(Core $core)
	{
		$this->core = $core;
	}

	public function log($level, $message, array $context=[])
	{
		$messages = &$this->get_stash()[$level];

		if ($messages)
		{
			$count = count($messages) + 1;
			$max = self::MAX_MESSAGES;

			if ($count >= $max)
			{
				$messages = array_splice($messages, $count - $max);
				array_unshift($messages, [ '*** SLICED', [] ]);
			}
		}

		$messages[] = [ $message, $context ];
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
		$stash = &$this->core->session->icanboogie_logger;

		return $stash;
	}
}
