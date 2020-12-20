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
final class Logger implements LoggerInterface
{
	public const MAX_MESSAGES = 50;

	use LoggerTrait;

	/**
	 * Returns the application's logger, create it if needed.
	 */
	static public function get_logger(Application $app): LoggerInterface
	{
		static $logger;

		if (!$logger)
		{
			$logger = new self($app);
		}

		return $logger;
	}

	/**
	 * Formats messages.
	 *
	 * @param array<array{0: string, 1: array<string, mixed>}> $messages The messages to format.
	 *
	 * @return string[]
	 */
	static private function format_messages(array $messages): array
	{
		$rc = [];

		foreach ($messages as $message_and_context)
		{
			[ $message, $context ] = $message_and_context;

			$rc[] = (string) self::format_message($message, $context);
		}

		return $rc;
	}

	/**
	 * Formats message with context.
	 *
	 * @param array<string, mixed> $context
	 */
	static private function format_message(string $message, array $context): string
	{
		return $context ? format($message, $context) : $message;
	}

	/**
	 * @var Application
	 */
	private $app;

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
	 */
	private function &get_stash(): array
	{
		$stash = &$this->app->session['flash_messages'];

		return $stash;
	}
}
