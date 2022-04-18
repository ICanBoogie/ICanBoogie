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
    use LoggerTrait;

    public const MAX_MESSAGES = 50;

    /**
     * Returns the application's logger, create it if needed.
     */
    public static function for_app(Application $app): LoggerInterface
    {
        static $logger;

        return $logger ??= new self($app);
    }

    /**
     * Formats messages.
     *
     * @param array<array{0: string, 1: array<string, mixed>}> $messages The messages to format.
     *
     * @return string[]
     */
    private static function format_messages(array $messages): array
    {
        $rc = [];

        foreach ($messages as $message_and_context) {
            [ $message, $context ] = $message_and_context;

            $rc[] = self::format_message($message, $context);
        }

        return $rc;
    }

    /**
     * Formats message with context.
     *
     * @param array<string, mixed> $context
     */
    private static function format_message(string $message, array $context): string
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
    public function log($level, $message, array $context = []): void
    {
        $messages = &$this->get_stash()[$level];
        $messages[] = [ $message, $context ];

        $count = count($messages);
        $max = self::MAX_MESSAGES;

        if ($count + 1 > $max) {
            $messages = array_splice($messages, $count - $max + 1);
            array_unshift($messages, [ '*** SLICED', [] ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function get_messages($level): array
    {
        $messages = &$this->get_stash()[$level];

        if (!$messages) {
            return [];
        }

        return self::format_messages($messages);
    }

    /**
     * @inheritdoc
     */
    public function fetch_messages($level): array
    {
        $messages = $this->get_messages($level);

        $this->get_stash()[$level] = [];

        return $messages;
    }

    /**
     * Returns stash reference.
     */
    private function &get_stash(): SessionFlash
    {
        return $this->app->session->flash;
    }
}
