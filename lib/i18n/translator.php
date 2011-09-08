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

use ICanBoogie\Operation\Users\IsInique;

use ICanBoogie\FileCache;
use ICanBoogie\I18n;
use ICanBoogie\I18n\Translator;
use ICanBoogie\Object;

class Translator extends Object implements \ArrayAccess
{
	private static $translators=array();

	/**
	 * Return the translator for the specified locale.
	 *
	 * @param string $id The locale identifier.
	 *
	 * @return Translator The translator for the locale.
	 */
	public static function get($id)
	{
		if (isset(self::$translators[$id]))
		{
			return self::$translators[$id];
		}

		self::$translators[$id] = $translator = new Translator($id);

		return $translator;
	}

	protected static $cache;

	protected static function get_cache()
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

			$messages += wd_array_flatten(require $filename);
		}

		return $messages;
	}

	/**
	 * @var array Translation messages.
	 */
	protected $messages;

	protected function __get_messages()
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
	 * @var Translator Fallback translator
	 */
	protected $fallback;

	/**
	 * Returns a translator fallback for this translator.
	 *
	 * @return Translator|null The translator fallback for this translator or null if there is
	 * none.
	 */
	protected function __get_fallback()
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
	 * @var string Locale id for this translator.
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
			$translated = self::format($translated, $args);
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

	/**
	 * Formats the given string by replacing placeholders with the given values.
	 *
	 * @param string $str The string to format.
	 * @param array $args An array of replacement for the plaecholders. Occurences in $str of any
	 * key in $args are replaced with the corresponding sanitized value. The sanitization function
	 * depends on the first character of the key:
	 *
	 * * :key: Replace as is. Use this for text that has already been sanitized.
	 * * !key: Sanitize using the `wd_entities()` function.
	 * * %key: Sanitize using the `wd_entities()` function and wrap inside a "EM" markup.
	 *
	 * Numeric indexes can also be used e.g '\2' or "{2}" are replaced by the value of the index
	 * "2".
	 *
	 * @return string
	 */
	static public function format($str, array $args=array())
	{
		if (!$args)
		{
			return $str;
		}

		$holders = array();

		$i = 0;

		foreach ($args as $key => $value)
		{
			$i++;

			if (is_array($value) || is_object($value))
			{
				$value = wd_dump($value);
			}
			else if (is_bool($value))
			{
				$value = $value ? 'true' : 'false';
			}
			else if (is_null($value))
			{
				$value = '<em>null</em>';
			}
			else if (is_string($key))
			{
				switch ($key{0})
				{
					case ':': break;
					case '!': $value = wd_entities($value); break;
					case '%': $value = '<em>' . wd_entities($value) . '</em>'; break;
				}
			}

			if (is_numeric($key))
			{
				$key = '\\' . $i;
				$holders['{' . $i . '}'] = $value;
			}

			$holders[$key] = $value;
		}

		return strtr($str, $holders);
	}
}