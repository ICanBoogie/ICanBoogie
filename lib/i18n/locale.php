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

use ICanBoogie;
use ICanBoogie\I18n\Formatter;
use ICanBoogie\Object;

/**
 *  @property array $conventions The UNICODE conventions for the locale.
 *  @property WdLocaleDateFormatter $date_formatter The data formater for the locale.
 *  @property WdLocaleNumberFormatter $number_formatter The number formater for the locale.
 *  @property WdLocaleTranslator $translator The translator for the locale.
 */

class Locale extends Object
{
	private static $locales=array();

	/**
	 * Returns a locale for the id.
	 *
	 * @param Locale $id The locale id.
	 *
	 * @return Locale.
	 */
	public static function get($id)
	{
		if (isset(self::$locales[$id]))
		{
			return self::$locales[$id];
		}

		self::$locales[$id] = $locale = new Locale($id);

		return $locale;
	}

	/**
	 * @var string Language for this locale.
	 */
	protected $language;

	/**
	 * @var string Territory for this locale.
	 */
	protected $territory;

	/**
	 * Constructor.
	 *
	 * @param string $id Locale identifier.
	 */
	protected function __construct($id)
	{
		list($this->language, $this->territory) = explode('_', $id) + array(1 => null);
	}

	/**
	 * Overrides the method to support composed properties for days, eras and months.
	 *
	 * The following pattern is supported for composed properties:
	 *
	 *     ^(standalone_)?(abbreviated|narrow|wide)_(days|eras|months|quarters)$
	 *
	 * For example, one can get the following properties:
	 *
	 *     $locale->abbreviated_months
	 *     $locale->standalone_abbreviated_months
	 *     $locale->wide_days
	 *     $locale->narrow_eras
	 *
	 * Fallbacks are available for the `narrows_eras` and `standalone_.+` properties:
	 *
	 * - If there is no definition available for narrow eras in the locale, the abbreviated
	 * convention is used instead.
	 * - If there is no stand-alone definition available, the "format" convention is used instead.
	 *
	 * @see Object::__get()
	 * @see http://unicode.org/reports/tr35/tr35-6.html#Calendar_Elements
	 *
	 * @param string $property
	 *
	 * @return mixed
	 */
	public function __get($property)
	{
		if (preg_match('#^(standalone_)?(abbreviated|narrow|wide)_(days|eras|months|quarters)$#', $property, $matches))
		{
			list(, $standalone, $width, $type) = $matches;

			if ($type == 'eras')
			{
				if ($width == 'narrow' && empty($this->conventions['dates'][$type][$width]))
				{
					$width = 'abbreviated';
				}

				$value = $this->conventions['dates'][$type][$width];
			}
			else
			{
				$context = $standalone ? 'stand-alone' : 'format';

				if ($standalone && empty($this->conventions['dates'][$type][$context][$width]))
				{
					$context = 'format';
				}

				if ($width == 'narrow' && empty($this->conventions['dates'][$type][$context][$width]))
				{
					$width = 'abbreviated';
				}

				if ($width == 'abbreviated' && empty($this->conventions['dates'][$type][$context][$width]))
				{
					$width = 'wide';
				}

				$value = $this->conventions['dates'][$type][$context][$width];
			}

			return $this->$property = $value;
		}

		return parent::__get($property);
	}

	/**
	 * Returns the conventions for this locale.
	 *
	 * The conventions are currently loaded from the <ICanBoogie\Root>locale/conventions directory.
	 *
	 * @return array the conventions for this locale.
	 */
	protected function __get_conventions()
	{
		$path = ICanBoogie\ROOT . 'locale' . DIRECTORY_SEPARATOR . 'conventions' . DIRECTORY_SEPARATOR;

		$id = null;
		$territory = $this->territory;
		$language = $this->language;

		if ($territory)
		{
			if (file_exists($path . $language . '_' . $territory . '.php'))
			{
				$id = $language . '_' . $territory;
			}
		}

		if (!$id && file_exists($path . $language . '.php'))
		{
			$id = $language;
		}

		if (!$id)
		{
			$id = 'en';
		}

		return require $path . $id . '.php';
	}

	/**
	 * @return Formatter\Number the number formatter for this locale.
	 */
	protected function __get_number_formatter()
	{
		return new Formatter\Number($this);
	}

	/**
	 * @return Formatter\Date the date formatter for this locale.
	 */
	protected function __get_date_formatter()
	{
		return new Formatter\Date($this);
	}

	/**
	 * @return Translator The translator for this locale.
	 */
	protected function __get_translator()
	{
		$id = $this->language;

		if ($this->territory)
		{
			$id .= '-' . $this->territory;
		}

		return Translator::get($id);
	}
}