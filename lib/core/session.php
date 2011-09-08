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
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 *
 * @property string $remote_ip The remote IP of the request that created the session.
 * @property string $remote_agent_hash The remote user agent hash of the request that created the
 * session.
 * @property string $token A token that can be used to prevent cross-site request forgeries.
 */
class Session
{
	/**
	 * Checks if a session identifier can be found to retrieve a session.
	 *
	 * @return bool true if the session identifier exists in the cookie, false otherwise.
	 */
	public static function exists()
	{
		global $core;

		return !empty($_COOKIE[$core->config['session']['name']]);
	}

	/**
	 * Constructor.
	 *
	 * In order to circumvent session fixation and session hijacking, the remote IP and the user
	 * agent hash are attached to the session. A previous session can only be restored if the
	 * remote address and the user agent hash match those attached to that previous session.
	 *
	 * Although the user agent is easily forgeable, the IP address (fetched from
	 * $_SERVER['REMOTE_ADDR']) is not forgeable without compromising the server itself. The
	 * values are stored independently in order to prevent a collision attack.
	 *
	 * The session is destroyed when the values don't match and the "location" header is set to
	 * request a reload.
	 *
	 * @param array $options
	 */
	public function __construct(array $options=array())
	{
		if (session_id())
		{
			return;
		}

		$options += array
		(
			'id' => null,
			'name' => 'ICanBoogie',
			'use_cookies' => true,
			'use_only_cookies' => true,
			'use_trans_sid' => false,
			'cache_limiter' => null,
			'module_name' => 'files'
		)

		+ session_get_cookie_params();

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

		session_start();

		#
		# The following line are meant to circumvent session fixation.
		#

		$remote_ip = $_SERVER['REMOTE_ADDR'];
		$remote_agent_hash = isset($_SERVER['HTTP_USER_AGENT']) ? md5($_SERVER['HTTP_USER_AGENT']) : null;

		if (empty($this->remote_ip))
		{
			$this->remote_ip = $remote_ip;
			$this->remote_agent_hash = $remote_agent_hash;
			$this->regenerate_token();
		}
		else if ($this->remote_ip != $remote_ip || $this->remote_agent_hash != $remote_agent_hash)
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
	 */
	public function regenerate_id($delete_old_session=false)
	{
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
		$_SESSION['token_time'] = time();

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
		return array_key_exists($property, $_SESSION);
	}

	public function __unset($property)
	{
		unset($_SESSION[$property]);
	}
}