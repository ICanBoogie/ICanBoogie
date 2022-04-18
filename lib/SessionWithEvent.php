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
 * Extends the {@link Session} class to fire `ICanBoogie\Session::start` when a session is started.
 */
final class SessionWithEvent extends Session
{
    static private SessionWithEvent $instance;

    public static function for_app(Application $app): self
    {
        return self::$instance ??= new self($app->config[AppConfig::SESSION]);
    }

    /**
     * @inheritdoc
     *
     * Fires `ICanBoogie\Session::start` event of class {@link Session\StartEvent}.
     */
    public function start(): bool
    {
        $started = parent::start();

        if ($started) {
            emit(new Session\StartEvent($this));
        }

        return $started;
    }
}
