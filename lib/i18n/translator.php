<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\I18n;

use ICanBoogie\FileCache;
use ICanBoogie\I18n;
use ICanBoogie\Object;

class Translator extends Object implements \ArrayAccess
{
	static private $translators=array();

	/**
	 * Return the translator for the specified locale.
	 *
	 * @param string $id The locale identifier.
	 *
	 * @return Translator The translator for the locale.
	 */
	static public function get($id)
	{
		if (isset(self::$translators[$id]))
		{
			return self::$translators[$id];
		}

		self::$translators[$id] = $translator = new static($id);

		return $translator;
	}

	static protected $cache;

	static protected function get_cache()
	{
		global $core;

		if (!self::$cache)
		{
			self::$cache = new FileCache
			(
				array
				(
					FileCache::T_COMPRESS => true,
					FileCache::T_REPOSITORY => $core->config['repository.cache'] . '/core',
					FileCache::T_SERIALIZE => true
				)
			);
		}

		return self::$cache;
	}

	static public function messages_construct($id)
	{
		$messages = array();

		foreach (I18n::$load_paths as $path)
		{
			$filename = $path . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR . $id . '.php';

			if (!file_exists($filename))
			{
				continue;
			}

			$messages += \ICanBoogie\array_flatten(require $filename);
		}

		return $messages;
	}

	/**
	 * Translation messages.
	 *
	 * @var array
	 */
	protected $messages;

	protected function get_messages()
	{
		global $core;

		$messages = array();
		$id = $this->id;

		if ($core->config['cache catalogs'])
		{
			$messages = self::get_cache()->load('i18n_' . $id, array(__CLASS__, 'messages_construct'), $id);
		}
		else
		{
			$messages = self::messages_construct($id);
		}

		if ($this->fallback)
		{
			$messages += $this->fallback->messages;
		}

		return $messages;
	}

	/**
	 * Fallback translator.
	 *
	 * @var Translator
	 */
	protected $fallback;

	/**
	 * Returns a translator fallback for this translator.
	 *
	 * @return Translator|null The translator fallback for this translator or null if there is
	 * none.
	 */
	protected function get_fallback()
	{
		list($id, $territory) = explode('-', $this->id) + array(1 => null);

		if (!$territory && $id == 'en')
		{
			return;
		}
		else if (!$territory)
		{
			$id = 'en';
		}

		return self::get($id);
	}

	/**
	 * Locale id for this translator.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Constructor.
	 *
	 * @param string $id Locale identifier
	 */
	protected function __construct($id)
	{
		unset($this->messages);
		unset($this->fallback);

		$this->id = $id;
	}

	//static public $missing=array();

	/**
	 * Translate a native string in a locale string.
	 *
	 * @param string $native The native string to translate.
	 * @param array $args
	 * @param array $options
	 *
	 * @return string The translated string, or the same native string if no translation could be
	 * found.
	 */
	public function __invoke($native, array $args=array(), array $options=array())
	{
		$native = (string) $native;
		$messages = $this->messages;
		$translated = null;

		$suffix = null;

		if ($args && array_key_exists(':count', $args))
		{
			$count = $args[':count'];

			if ($count == 0)
			{
				$suffix = '.none';
			}
			else if ($count == 1)
			{
				$suffix = '.one';
			}
			else
			{
				$suffix = '.other';
			}
		}

		$scope = I18n::get_scope();

		if (isset($options['scope']))
		{
			if ($scope)
			{
				$scope .= '.';
			}

			$scope .= is_array($options['scope']) ? implode('.', $options['scope']) : $options['scope'];
		}

		$prefix = $scope;

		while ($scope)
		{
			$try = $scope . '.' . $native . $suffix;

			if (isset($messages[$try]))
			{
				$translated = $messages[$try];

				break;
			}

			$pos = strpos($scope, '.');

			if ($pos === false)
			{
				break;
			}

			$scope = substr($scope, $pos + 1);
		}

		if (!$translated)
		{
			if (isset($messages[$native . $suffix]))
			{
				$translated = $messages[$native . $suffix];
			}
		}

		if (!$translated)
		{
			//self::$missing[] = ($prefix ? $prefix . '.' : '') . $native;

			if (!empty($options['default']))
			{
				$default = $options['default'];

				if ($default instanceof \Closure)
				{
					return $default($native);
				}

				$native = $options['default'];
				unset($options['default']);

				return $this->__invoke($native, $args, $options);
			}

			#
			# We couldn't find any translation for the native string provide, in order to avoid
			# another search for the same string, we store the native string as the translation in
			# the locale messages.
			#

			$this->messages[($prefix ? $prefix . '.' : '') . $native] = $native;

			$translated = $native;
		}

		if ($args)
		{
			$translated = \ICanBoogie\format($translated, $args);
		}

		return $translated;
	}

	public function offsetExists($offset)
	{
		return isset($this->messages[$offset]);
	}

	public function offsetGet($offset)
	{
		return isset($this->messages[$offset]) ? $this->messages[$offset] : null;
	}

	public function offsetSet($offset, $value)
	{

	}

	public function offsetUnset($offset)
	{

	}
}