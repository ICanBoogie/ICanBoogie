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

use ICanBoogie\Exception;

/**
 * Provides date and time localization.
 *
 * The class allows you to format dates and times in a locale-sensitive manner using
 * {@link http://www.unicode.org/reports/tr35/#Date_Format_Patterns Unicode format patterns}.
 *
 * Original code: http://code.google.com/p/yii/source/browse/tags/1.1.7/framework/i18n/CDateFormatter.php
 */
class DateFormatter
{
	/**
	 * @var array Pattern characters mapping to the corresponding translator methods
	 */
	static private $formatters = array
	(
		'G' => 'format_era',
		'y' => 'format_year',
//		'Y' => Year (in "Week of Year" based calendars).
//		'u' => Extended year.
		'Q' => 'format_quarter',
		'q' => 'format_standalone_quarter',
		'M' => 'format_month',
		'L' => 'format_standalone_month',
//		'l' => Special symbol for Chinese leap month, used in combination with M. Only used with the Chinese calendar.
		'w' => 'format_week_of_year',
		'W' => 'format_week_of_month',
		'd' => 'format_day_of_month',
		'D' => 'format_day_of_year',
		'F' => 'format_day_of_week_in_month',

		'h' => 'format_hour12',
		'H' => 'format_hour24',
		'm' => 'format_minutes',
		's' => 'format_seconds',
		'E' => 'format_day_in_week',
		'c' => 'format_day_in_week',
		'e' => 'format_day_in_week',
		'a' => 'format_period',
		'k' => 'format_hour_in_day',
		'K' => 'format_hour_in_period',
		'z' => 'format_timezone',
		'Z' => 'format_timezone',
		'v' => 'format_timezone'
	);

	private $locale;

	/**
	 * Constructor.
	 *
	 * @param Locale $locale
	 */
	public function __construct(Locale $locale)
	{
		$this->locale = $locale;
	}

	public static function get_date($time=null, $gmt=false)
	{
		if ($gmt)
		{
			$tz = date_default_timezone_get();
			date_default_timezone_set('GMT');
			$rc = getdate($time);
			date_default_timezone_set($tz);
		}
		else
		{
			$rc = getdate($time);
		}

		return $rc;
	}

	/**
	 * Parses the datetime format pattern.
	 *
	 * @param string $pattern the pattern to be parsed
	 *
	 * @return array tokenized parsing result
	 */
	protected function parse_format($pattern)
	{
		static $formats = array();

		if (isset($formats[$pattern]))
		{
			return $formats[$pattern];
		}

		$tokens = array();
		$is_literal = false;
		$literal = '';

		for ($i = 0, $n = strlen($pattern) ; $i < $n ; ++$i)
		{
			$c = $pattern{$i};

			if ($c === "'")
			{
				if ($i < $n-1 && $pattern{$i+1} === "'")
				{
					$tokens[] = "'";
					$i++;
				}
				else if ($is_literal)
				{
					$tokens[] = $literal;
					$literal = '';
					$is_literal = false;
				}
				else
				{
					$is_literal = true;
					$literal = '';
				}
			}
			else if ($is_literal)
			{
				$literal .= $c;
			}
			else
			{
				for ($j = $i + 1 ; $j < $n ; ++$j)
				{
					if ($pattern{$j} !== $c) break;
				}

				$l = $j-$i;
				$p = str_repeat($c, $l);

				$tokens[] = isset(self::$formatters[$c]) ? array(self::$formatters[$c], $p, $l) : $p;

				$i = $j - 1;
			}
		}

		if ($literal)
		{
			$tokens[] = $literal;
		}

		return $formats[$pattern] = $tokens;
	}

	/**
	 * Formats a date according to a customized pattern.
	 *
	 * @param string $pattern the pattern (See {@link http://www.unicode.org/reports/tr35/#Date_Format_Patterns})
	 * @param mixed $time UNIX timestamp, a string in strtotime format, or a {@link \DateTime} object
	 *
	 * @return string formatted date time.
	 */
	public function format($time, $pattern)
	{
		if (is_array($time))
		{
			$date = $time;
		}
		else
		{
			if ($time instanceof \DateTime)
			{
				$time = $time->getTimestamp();
			}
			else if (!is_numeric($time))
			{
				$time = strtotime($time);
			}

			$date = self::get_date($time);
		}

		$tokens = $this->parse_format($pattern);

		$rc = '';

		foreach ($tokens as $token)
		{
			if (is_array($token)) // a callback: method name, sub-pattern
			{
				$token = $this->{$token[0]}($date, $token[1], $token[2]);
			}

			$rc .= $token;
		}

		return $rc;
	}

	public function __invoke($time, $pattern)
	{
		return $this->format($time, $pattern);
	}

	/**
	 * Formats a date according to a predefined pattern.
	 * The predefined pattern is determined based on the date pattern width and time pattern width.
	 *
	 * @param mixed $timestamp UNIX timestamp or a string in strtotime format
	 * @param string $dateWidth width of the date pattern. It can be 'full', 'long', 'medium' and 'short'.
	 * If null, it means the date portion will NOT appear in the formatting result
	 * @param string $timeWidth width of the time pattern. It can be 'full', 'long', 'medium' and 'short'.
	 * If null, it means the time portion will NOT appear in the formatting result
	 *
	 * @return string formatted date time.
	 */
	public function format_datetime($timestamp, $date_pattern='medium', $time_pattern='medium')
	{
		$date = null;
		$time = null;

		$dates_conventions = $this->locale->conventions['dates'];
		$available_formats = $dates_conventions['dateTimeFormats'];

		if ($date_pattern)
		{
			$date_widths = $dates_conventions['dateFormats'];

			if (isset($date_widths[$date_pattern]))
			{
				$date_pattern = $date_widths[$date_pattern];
			}
			else if (isset($available_formats[$date_pattern]))
			{
				$date_pattern = $available_formats[$date_pattern];
			}

			$date = $this->format($timestamp, $date_pattern);
		}

		if ($time_pattern)
		{
			$time_widths = $dates_conventions['timeFormats'];

			if (isset($time_widths[$time_pattern]))
			{
				$time_pattern = $time_widths[$time_pattern];
			}
			else if (isset($available_formats[$time_pattern]))
			{
				$date_pattern = $available_formats[$time_pattern];
			}

			$time = $this->format($timestamp, $time_pattern);
		}

		if ($date && $time)
		{
			$date_time_pattern = isset($dates_conventions['date_time_format']) ? $dates_conventions['date_time_format'] : '{1} {0}';

			return strtr($date_time_pattern, array('{0}' => $time, '{1}' => $date));
		}

		return $date . $time;
	}

	/**
	 * Formats quarter.
	 *
	 * Use one or two for the numerical quarter, three for the abbreviation, or four for the wide
	 * name: Q, QQ, QQQ or QQQQ.
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 * @return string formatted quarter.
	 */
	protected function format_quarter(array $date, $pattern, $length)
	{
		$month = $date['mon'] - 1;
		$quarter = ceil(($month - 1) / 3);

		switch ($length)
		{
			case 1: return $quarter;
			case 2: return str_pad($quarter, 2, '0', STR_PAD_LEFT);
			case 3: return $this->locale->abbreviated_quarters[$quarter];
			case 4: return $this->locale->wide_quarters[$quarter];
		}

		throw new Exception('The pattern for quarter must be "Q", "QQ", "QQQ", "QQQQ". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Formats stand-alone quarter.
	 *
	 * Use one or two for the numerical quarter, three for the abbreviation, or four for the full
	 * name: q, qq, qqq or qqqq.
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 * @return string formatted stand-alone quarter.
	 */
	protected function format_standalone_quarter(array $date, $pattern, $length)
	{
		$month = $date['mon'] - 1;
		$quarter = ceil(($month - 1) / 3);

		switch ($length)
		{
			case 1: return $quarter;
			case 2: return str_pad($quarter, 2, '0', STR_PAD_LEFT);
			case 3: return $this->locale->standalone_abbreviated_quarters[$quarter];
			case 4: return $this->locale->standalone_wide_quarters[$quarter];
		}

		throw new Exception('The pattern for stand-alone quarter must be "q", "qq", "qqq" or "qqqq". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Era - Replaced with the Era string for the current date. One to three letters for the
	 * abbreviated form, four letters for the long form, five for the narrow form.
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 * @return string era
	 * @todo How to support multiple Eras?, e.g. Japanese.
	 */
	protected function format_era(array $date, $pattern, $length)
	{
		$era = ($date['year'] > 0) ? 1 : 0;

		switch($length)
		{
			case 1:
			case 2:
			case 3: return $this->locale->abbreviated_eras[$era];
			case 4: return $this->locale->wide_eras[$era];
			case 5: return $this->locale->narrow_eras[$era];
		}

		throw new Exception('The pattern for era must be "G", "GG", "GGG", "GGGG" or "GGGGG". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Get the year.
 	 * "yy" will return the last two digits of year.
 	 * "y...y" will pad the year with 0 in the front, e.g. "yyyyy" will generate "02008" for year 2008.
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 * @return string formatted year
	 */
	protected function format_year(array $date, $pattern, $length)
	{
		$year = $date['year'];

		if ($length == 2)
		{
			$year = $year % 100;
		}

		return str_pad($year, $length, '0', STR_PAD_LEFT);
	}

	/**
	 * Formats the month.
	 *
	 * Use one or two for the numerical month, three for the abbreviation, or four for the full
	 * name, or five for the narrow name: "M", "MM", "MMM", "MMMM" or "MMMMM".
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 * @return string formated month.
	 */
	protected function format_month(array $date, $pattern, $length)
	{
		$month = $date['mon'];

		switch ($length)
		{
			case 1: return $month;
			case 2: return str_pad($month, 2, '0', STR_PAD_LEFT);
			case 3: return $this->locale->abbreviated_months[$month];
			case 4: return $this->locale->wide_months[$month];
			case 5: return $this->locale->narrow_months[$month];
		}

		throw new Exception('The pattern for month must be "M", "MM", "MMM", "MMMM". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Formats the stand-alone month.
	 *
	 * Use one or two for the numerical month, three for the abbreviation, or four for the full
	 * name, or 5 for the narrow name: "L", "LL", "LLL", "LLLL" or "LLLLL".
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 *
	 * @return string formated month.
	 */
	protected function format_standalone_month(array $date, $pattern, $length)
	{
		$month = $date['mon'];

		switch ($length)
		{
			case 1: return $month;
			case 2: return str_pad($month, 2, '0', STR_PAD_LEFT);
			case 3: return $this->locale->standalone_abbreviated_months[$month];
			case 4: return $this->locale->standalone_wide_months[$month];
			case 5: return $this->locale->standalone_narrow_months[$month];
		}

		throw new Exception('The pattern for stand-alone month must be "L", "LL", "LLL" or "LLLL". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Week of Year.
	 *
	 * Use one or two for the numerical week of year: "w" or "ww".
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 *
	 * @return integer week in year
	 */
	protected function format_week_of_year(array $date, $pattern, $length)
	{
		if ($length < 3)
		{
			$week = (int) date('W', $date[0]);

			return $length == 1 ? $week : str_pad($week, 2, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for week of year must be "w" or "ww". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Week of month.
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 *
	 * @return integer week of month
	 */
	protected function format_week_of_month(array $date, $pattern, $length)
	{
		if ($length == 1)
		{
			return ceil($date['mday'] / 7);
		}

		throw new Exception('The pattern for week in month must be "W". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Day of the month.
	 *
	 * Use one or two for the numerical day of the month: "d" or "dd".
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 *
	 * @return string day of the month
	 */
	protected function format_day_of_month(array $date, $pattern, $length)
	{
		$day = $date['mday'];

		if ($length == 1)
		{
			return $day;
		}
		else if ($length == 2)
		{
			return str_pad($day, 2, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for day of the month must be "d" or "dd". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Day of year.
	 *
	 * Use one to three for the numerical day of year: "D", "DD" or "DDD".
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 *
	 * @return string Formated day oy year.
	 */
	protected function format_day_of_year(array $date, $pattern, $length)
	{
		$day = $date['yday'];

		if ($length < 4)
		{
			return str_pad($day + 1, $length, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for day in year must be "D", "DD" or "DDD". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Day of Week in Month. The example is for the 2nd Wed in July.
	 *
	 * @param array $date result of getdate().
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 *
	 * @return integer day in month
	 */
	protected function format_day_of_week_in_month(array $date, $pattern, $length)
	{
		if ($length == 1)
		{
			return (int)(($date['mday']+6)/7);
		}

		throw new Exception('The pattern for day in month must be "F". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Get the day of the week.
	 *
 	 * "E", "EE", "EEE" will return abbreviated week day name, e.g. "Tues";
 	 * "EEEE" will return full week day name;
 	 * "EEEEE" will return the narrow week day name, e.g. "T";
 	 *
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 *
	 * @return string day of the week.
	 *
	 * @see http://www.unicode.org/reports/tr35/#Date_Format_Patterns
	 */
	protected function format_day_in_week(array $date, $pattern)
	{
		static $translate = array
		(
			0 => 'sun',
			1 => 'mon',
			2 => 'tue',
			3 => 'wed',
			4 => 'thu',
			5 => 'fri',
			6 => 'sat'
		);

		$day = $date['wday'];

		switch ($pattern)
		{
			case 'E':
			case 'EE':
			case 'EEE':
			case 'eee':
				return $this->locale->abbreviated_days[$translate[$day]];

			case 'EEEE':
			case 'eeee':
				return $this->locale->wide_days[$translate[$day]];

			case 'EEEEE':
			case 'eeeee':
				return $this->locale->narrow_days[$translate[$day]];

			case 'e':
			case 'ee':
			case 'c':
				return $day ? $day : 7;

			case 'ccc':
				return $this->locale->standalone_abbreviated_days[$translate[$day]];

			case 'cccc':
				return $this->locale->standalone_wide_days[$translate[$day]];

			case 'ccccc':
				return $this->locale->standalone_narrow_days[$translate[$day]];
		}

		throw new Exception('The pattern for day of the week must be "E", "EE", "EEE", "EEEE", "EEEEE", "e", "ee", "eee", "eeee", "eeeee", "c", "cccc" or "ccccc". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Get the AM/PM designator, 12 noon is PM, 12 midnight is AM.
	 *
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 *
	 * @return string AM or PM designator
	 */
	protected function format_period(array $date, $pattern, $length)
	{
		return $this->locale->conventions['dates']['dayPeriods']['format']['abbreviated'][((int) $date['hours'] / 12) ? 'pm' : 'am'];
	}

	/**
	 * Get the hours in 12 hour format, i.e., [1-12]
	 * "h" for non-padding, "hh" will always return 2 characters.
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 * @return string hours in 12 hour format.
	 */
	protected function format_hour12(array $date, $pattern, $length)
	{
		$hour = $date['hours'];
		$hour = ($hour == 12 | $hour == 0) ? 12 : $hour % 12;

		if ($length == 1)
		{
			return $hour;
		}
		else if ($length == 2)
		{
			return str_pad($hour, 2, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for 12 hour format must be "h" or "hh". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Get the hours in 24 hour format, i.e. [0-23].
	 * "H" for non-padding, "HH" will always return 2 characters.
	 * @param string $pattern a pattern.
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param int $length Number of repetition.
	 * @return string hours in 24 hour format.
	 */
	protected function format_hour24(array $date, $pattern, $length)
	{
		$hour = $date['hours'];

		if ($length == 1)
		{
			return $hour;
		}
		else if ($length == 2)
		{
			return str_pad($hour, 2, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for 24 hour format must be "H" or "HH". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Get the hours in AM/PM format, e.g [0-11]
	 * "K" for non-padding, "KK" will always return 2 characters.
	 * @param string $pattern a pattern.
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param int $length Number of repetition.
	 * @return integer hours in AM/PM format.
	 */
	protected function format_hour_in_period(array $date, $pattern, $length)
	{
		$hour = $date['hours'] % 12;

		if ($length == 1)
		{
			return $hour;
		}
		else if ($length == 2)
		{
			return str_pad($hour, 2, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for hour in AM/PM must be "K" or "KK". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Get the hours [1-24].
	 * 'k' for non-padding, and 'kk' with 2 characters padding.
	 * @param string $pattern a pattern.
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param int $length Number of repetition.
	 * @return integer hours [1-24]
	 */
	protected function format_hour_in_day(array $date, $pattern, $length)
	{
		$hour = $date['hours'] == 0 ? 24 : $date['hours'];

		if ($length == 1)
		{
			return $hour;
		}
		else if ($length == 2)
		{
			return str_pad($hour, 2, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for hour in day must be "k" or "kk". Given: %given', array('%given' => $pattern));
	}

	/**
	 * Get the minutes.
	 * "m" for non-padding, "mm" will always return 2 characters.
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param string $pattern a pattern.
	 * @param int $length Number of repetition.
	 * @return string minutes.
	 */
	protected function format_minutes(array $date, $pattern, $length)
	{
		$minutes = $date['minutes'];

		if ($length == 1)
		{
			return $minutes;
		}
		else if ($length == 2)
		{
			return str_pad($minutes, 2, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for minutes must be "m" or "mm".');
	}

	/**
	 * Get the seconds.
	 * "s" for non-padding, "ss" will always return 2 characters.
	 * @param string $pattern a pattern.
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param int $length Number of repetition.
	 * @return string seconds
	 */
	protected function format_seconds(array $date, $pattern, $length)
	{
		$seconds = $date['seconds'];

		if ($length == 1)
		{
			return $seconds;
		}
		else if ($length == 2)
		{
			return str_pad($seconds, 2, '0', STR_PAD_LEFT);
		}

		throw new Exception('The pattern for seconds must be "s" or "ss".');
	}

	/**
	 * Get the timezone of the server machine.
	 * @param string $pattern a pattern.
	 * @param array $date result of {@link CTimestamp::getdate}.
	 * @param int $length Number of repetition.
	 * @return string time zone
	 * @todo How to get the timezone for a different region?
	 */
	protected function format_timezone(array $date, $pattern, $length)
	{
		if ($pattern{0} === 'z' || $pattern{0} === 'v')
		{
			return date('T', $date[0]);
		}
		else if ($pattern{0} === 'Z')
		{
			return date('O', $date[0]);
		}

		throw new Exception('The pattern for time zone must be "z" or "v".');
	}
}