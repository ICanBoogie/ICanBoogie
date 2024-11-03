<?php

namespace ICanBoogie;

use ICanBoogie\Session\StartEvent;

/**
 * Extends the {@see Session} to emit {@see StartEvent} when the session starts.
 */
final class SessionWithEvent extends Session
{
    private static SessionWithEvent $instance;

    public static function for_app(Application $app): self
    {
        return self::$instance ??= new self($app->config->session);
    }

    /**
     * @inheritdoc
     *
     * Emits {@see StartEvent} when the session starts.
     */
    public function start(): bool
    {
        $started = parent::start();

        if ($started) {
            emit(new StartEvent($this));
        }

        return $started;
    }
}
