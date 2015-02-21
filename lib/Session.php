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
 * Session.
 *
 * @property string $remote_agent_hash The remote user agent hash of the request that created the
 * session.
 * @property Logger $icanboogie_logger
 * @property string $token A token that can be used to prevent cross-site request forgeries.
 */
class Session
{
	static public $defaults = [

		'id' => null,
		'name' => 'ICanBoogie',
		'domain' => null,
		'use_cookies' => true,
		'use_only_cookies' => true,
		'use_trans_sid' => false,
		'cache_limiter' => null,
		'module_name' => 'files'

	];

	/**
	 * Checks if a session identifier can be found to retrieve a session.
	 *
	 * @return bool true if the session identifier exists in the cookie, false otherwise.
	 */
	static public function exists()
	{
		return !empty($_COOKIE[app()->config['session']['name']]);
	}

	/**
	 * Returns a Session instance.
	 *
	 * The session is initialized when the session object is created.
	 *
	 * Once the session is created the `start` event is fired with the session as sender.
	 *
	 * @param Core $app
	 *
	 * @return Session
	 */
	static function get_session(Core $app)
	{
		$options = $app->config['session'];

		unset($options['id']);

		return new static($options);
	}

	/**
	 * Constructor.
	 *
	 * In order to circumvent session fixation and session hijacking, the user agent hash is
	 * attached to the session. A previous session can only be restored if the
	 * user agent hash match.
	 *
	 * The session is destroyed when the values don't match and the "location" header is set to
	 * request a reload.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		if (session_id())
		{
			return;
		}

		$options = $this->prepare_options($options);

		$this->prepare($options);

		if (PHP_SAPI != 'cli')
		{
			session_start();
			$this->check_fixation($options);
		}

		new Session\StartEvent($this);
	}

    /**
     * Prepare session options.
     *
     * @param array $options
     *
     * @return array
     */
    protected function prepare_options(array $options)
    {
        return $options + self::$defaults + session_get_cookie_params();
    }

	/**
	 * Prepare the session environment.
	 *
	 * @param array $options
	 */
    protected function prepare(array $options)
	{
		$id = $options['id'];

		if ($id)
		{
			session_id($id);
		}

		session_name($options['name']);
		session_set_cookie_params($options['lifetime'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);

		if ($options['cache_limiter'] !== null)
		{
			session_cache_limiter($options['cache_limiter']);
		}

		if ($options['module_name'] != session_module_name())
		{
			session_module_name($options['module_name']);
		}

		$use_trans_sid = $options['use_trans_sid'];
		ini_set('session.use_trans_sid', $use_trans_sid);

		if ($use_trans_sid)
		{
			output_add_rewrite_var(session_name(), session_id());
		}
		else
		{
			output_reset_rewrite_vars();
		}
	}

	/**
	 * We do what we can to prevent session fixation.
	 *
	 * @param array $options
	 */
	private function check_fixation(array $options)
	{
		$remote_agent_hash = isset($_SERVER['HTTP_USER_AGENT']) ? md5($_SERVER['HTTP_USER_AGENT']) : null;

		if (empty($this->remote_agent_hash))
		{
			$this->remote_agent_hash = $remote_agent_hash;
			$this->regenerate_token();
		}
		else if ($this->remote_agent_hash != $remote_agent_hash)
		{
			session_destroy();

			header('Location: ' . $_SERVER['REQUEST_URI']);

			if ($options['use_cookies'])
			{
				setcookie(session_name(), '', time() - 42000, $options['path'], $options['domain'], $options['secure'], $options['httponly']);
			}

			exit;
		}
	}

	/**
	 * Regenerates the id of the session.
	 *
	 * @param bool $delete_old_session
	 *
	 * @return bool|null `true` when the id is regenerated, `false` when it is not, `null` when
	 * the application is running from CLI.
	 */
	public function regenerate_id($delete_old_session=false)
	{
		if (PHP_SAPI == 'cli')
		{
			return null;
		}

		return session_regenerate_id($delete_old_session);
	}

	/**
	 * Regenerates the session token.
	 *
	 * The `token_time` property is updated to the current time.
	 *
	 * @return string The new session token.
	 */
	public function regenerate_token()
	{
		$_SESSION['token'] = $token = md5(uniqid());
		$_SESSION['token_time'] = microtime(true);

		return $token;
	}

	public function &__get($property)
	{
		return $_SESSION[$property];
	}

	public function __set($property, $value)
	{
		$_SESSION[$property] = $value;
	}

	public function __isset($property)
	{
		return isset($_SESSION, $property);
	}

	public function __unset($property)
	{
		unset($_SESSION[$property]);
	}
}
