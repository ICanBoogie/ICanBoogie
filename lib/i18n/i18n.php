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

use ICanBoogie\I18n\Locale;

class I18n
{
	/**
	 * Paths were messages catalogs can be found.
	 *
	 * @var array
	 */
	public static $load_paths = array();

	/**
	 * The currently used language.
	 *
	 * @var string
	 */
	private static $language = 'en';

	/**
	 * Changes the current language.
	 *
	 * @param string $id
	 */
	public static function set_language($id)
	{
		self::$language = $id;
	}

	/**
	 * Returns the current locale ID.
	 *
	 * @return string
	 */
	public static function get_language()
	{
		return self::$language;
	}

	/**
	 * The current Locale object.
	 *
	 * @var Locale
	 */
	private static $locale;

	/**
	 * Returns the current Locale object.
	 *
	 * @return Locale
	 */
	public static function get_locale()
	{
		if (!self::$locale)
		{
			self::$locale = Locale::get(self::$language);
		}

		return self::$locale;
	}

	private static $translators=array();

	/**
	 * Translates a string to the current language or a given language.
	 *
	 * @param string $str The native string to translate.
	 * @param array $args Arguments used to format the translated string.
	 * @param array $options Options for the translation.
	 *
	 * @return string The translated string.
	 */
	public static function translate($str, array $args=array(), array $options=array())
	{
		$id = empty($options['language']) ? self::$language : $options['language'];

		if (empty(self::$translators[$id]))
		{
			self::$translators[$id] = Locale::get($id)->translator;
		}

		return self::$translators[$id]->__invoke($str, $args, $options);
	}

	private static $scope;
	private static $scope_chain = array();

	public static function push_scope($scope)
	{
		array_push(self::$scope_chain, self::$scope);

		self::$scope = (array) $scope;
	}

	public static function pop_scope()
	{
		self::$scope = array_pop(self::$scope_chain);
	}

	public static function get_scope()
	{
		$scope = self::$scope;

		if (is_array($scope))
		{
			$scope = implode('.', $scope);
		}

		return $scope;
	}
}