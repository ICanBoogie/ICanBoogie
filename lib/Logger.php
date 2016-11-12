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
 * A message logger using the application's session to store the messages.
 */
class Logger implements LoggerInterface
{
	const MAX_MESSAGES = 50;

	use LoggerTrait;

	/**
	 * Returns the application's logger, create it if needed.
	 *
	 * @param Application $app
	 *
	 * @return Logger
	 */
	static public function get_logger(Application $app)
	{
		static $logger;

		if (!$logger)
		{
			$logger = new static($app);
		}

		return $logger;
	}

	/**
	 * Formats messages.
	 *
	 * @param array $messages The messages to format.
	 *
	 * @return array
	 */
	static private function format_messages(array $messages)
	{
		$rc = [];

		foreach ($messages as $message_and_context)
		{
			list($message, $context) = $message_and_context;

			$rc[] = (string) self::format_message($message, $context);
		}

		return $rc;
	}

	/**
	 * Formats message with context.
	 *
	 * @param string $message
	 * @param array $context
	 *
	 * @return string
	 */
	static private function format_message($message, $context)
	{
		return $context ? format($message, $context) : $message;
	}

	private $app;

	/**
	 * Initialize the {@link $app} property.
	 *
	 * @param Application $app
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	/**
	 * @inheritdoc
	 */
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

	/**
	 * @inheritdoc
	 */
	public function get_messages($level)
	{
		$messages = &$this->get_stash()[$level];

		if (!$messages)
		{
			return [];
		}

		return self::format_messages($messages);
	}

	/**
	 * @inheritdoc
	 */
	public function fetch_messages($level)
	{
		$messages = $this->get_messages($level);

		$this->get_stash()[$level] = [];

		return $messages;
	}

	/**
	 * Returns stash reference.
	 *
	 * @return array
	 */
	private function &get_stash()
	{
		$stash = &$this->app->session['flash_messages'];

		return $stash;
	}
}
