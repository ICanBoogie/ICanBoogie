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
class SessionWithEvent extends Session
{
	/**
	 * @var static
	 */
	static private $instance;

	/**
	 * @param Application $app
	 *
	 * @return static
	 */
	static public function for_app(Application $app)
	{
		return self::$instance ?: self::$instance = new static($app->config[AppConfig::SESSION]);
	}

	/**
	 * @inheritdoc
	 *
	 * Fires `ICanBoogie\Session::start` event of class {@link Session\StartEvent}.
	 */
	public function start()
	{
		$started = parent::start();

		if ($started)
		{
			new Session\StartEvent($this);
		}

		return $started;
	}
}
