<?php

namespace ICanBoogie\I18n;

function t($str, array $args=array(), array $options=array())
{
	return \ICanBoogie\I18n::translate($str, $args, $options);
}

/**
 * Formats a size in "b", "Kb", "Mb", "Gb" or "Tb".
 *
 * @param int $size
 *
 * @return string
 */
function format_size($size)
{
	if ($size < 1024)
	{
		$str = ":size\xC2\xA0b";
	}
	else if ($size < 1024 * 1024)
	{
		$str = ":size\xC2\xA0Kb";
		$size = $size / 1024;
	}
	else if ($size < 1024 * 1024 * 1024)
	{
		$str = ":size\xC2\xA0Mb";
		$size = $size / (1024 * 1024);
	}
	else if ($size < 1024 * 1024 * 1024 * 1024)
	{
		$str = ":size\xC2\xA0Gb";
		$size = $size / (1024 * 1024 * 1024);
	}
	else
	{
		$str = ":size\xC2\xA0Tb";
		$size = $size / (1024 * 1024 * 1024 * 1024);
	}

	return t($str, array(':size' => round($size)));
}

function format_number($number)
{
	$decimal_point = \ICanBoogie\I18n::get_locale()->conventions['numbers']['symbols']['decimal'];
	$thousands_sep = ' ';

	return number_format($number, ($number - floor($number) < .009) ? 0 : 2, $decimal_point, $thousands_sep);
}

function format_currency($value, $currency)
{
	return \ICanBoogie\I18n::get_locale()->number_formatter->format_currency($value, $currency);
}

function format_date($time, $pattern='default')
{
	$locale = \ICanBoogie\I18n::get_locale();

	if ($pattern == 'default')
	{
		$pattern = $locale->conventions['dates']['dateFormats']['default'];
	}

	if (isset($locale->conventions['dates']['dateFormats'][$pattern]))
	{
		$pattern = $locale->conventions['dates']['dateFormats'][$pattern];
	}

	return $locale->date_formatter->format($time, $pattern);
}

function format_datetime($time, $date_pattern='default', $time_pattern='default')
{
	if (is_string($time))
	{
		$time = strtotime($time);
	}

	$locale = \ICanBoogie\I18n::get_locale();

	if (isset($locale->conventions['dates']['dateTimeFormats']['availableFormats'][$date_pattern]))
	{
		$date_pattern = $locale->conventions['dates']['dateTimeFormats']['availableFormats'][$date_pattern];
		$time_pattern = null;
	}

	return $locale->date_formatter->format_datetime($timestamp, $date_pattern, $time_pattern);
}

function date_period($date)
{
	static $relative;

	if (is_numeric($date))
	{
		$date_secs = $date;
		$date = date('Y-m-d', $date);
	}
	else
	{
		$date_secs = strtotime($date);
	}

	$today_days = strtotime(date('Y-m-d')) / (60 * 60 * 24);
	$date_days = strtotime(date('Y-m-d', $date_secs)) / (60 * 60 * 24);

	$diff = round($date_days - $today_days);
	$locale_id = \ICanBoogie\I18n::get_language();

	if (empty($relative[$locale_id]))
	{
		$relative[$locale_id] = \ICanBoogie\I18n::get_locale()->conventions['dates']['fields']['day']['relative'];
	}

	if (isset($relative[$locale_id][$diff]))
	{
		return $relative[$locale_id][$diff];
	}
	else if ($diff > -6)
	{
		return ucfirst(format_date($date_secs, 'EEEE'));
	}

	return format_date($date);
}