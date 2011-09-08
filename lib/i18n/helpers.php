<?php

use ICanBoogie\I18n\Locale;

function t($str, array $args=array(), array $options=array())
{
	global $core;
	static $translators=array();

	$id = isset($options['language']) ? $options['language'] : $core->language;

	if (empty($translators[$id]))
	{
		$translators[$id] = Locale::get($id)->translator;
	}

	return $translators[$id]->__invoke($str, $args, $options);
}

function wd_format_size($size)
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
	else
	{
		$str = ":size\xC2\xA0Gb";
		$size = $size / (1024 * 1024 * 1024);
	}

	return t($str, array(':size' => round($size)));
}

function wd_format_number($number)
{
	global $core;

	$decimal_point = $core->locale->conventions['numbers']['symbols']['decimal'];
	$thousands_sep = ' ';

	return number_format($number, ($number - floor($number) < .009) ? 0 : 2, $decimal_point, $thousands_sep);
}

function wd_format_currency($value, $currency)
{
	global $core;

	return $core->locale->number_formatter->format_currency($value, $currency);
}

function wd_format_date($time, $pattern='default')
{
	global $core;

	$locale = $core->locale;

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

function wd_format_datetime($time, $date_pattern='default', $time_pattern='default')
{
	global $core;

	if (is_string($time))
	{
		$time = strtotime($time);
	}

	$locale = $core->locale;

	if (isset($locale->conventions['dates']['dateTimeFormats']['availableFormats'][$date_pattern]))
	{
		$date_pattern = $locale->conventions['dates']['dateTimeFormats']['availableFormats'][$date_pattern];
		$time_pattern = null;
	}

	return $locale->date_formatter->format_datetime($timestamp, $date_pattern, $time_pattern);
}

function wd_array_flatten($array, $separator='.', $depth=0)
{
	$rc = array();

	if (is_array($separator))
	{
		foreach ($array as $key => $value)
		{
			if (!is_array($value))
			{
				$rc[$key . ($depth ? $separator[1] : '')] = $value;

				continue;
			}

			$values = wd_array_flatten($value, $separator, $depth + 1);

			foreach ($values as $vkey => $value)
			{
				$rc[$key . ($depth ? $separator[1] : '') . $separator[0] . $vkey] = $value;
			}
		}
	}
	else
	{
		foreach ($array as $key => $value)
		{
			if (!is_array($value))
			{
				$rc[$key] = $value;

				continue;
			}

			$values = wd_array_flatten($value, $separator, $depth + 1);

			foreach ($values as $vkey => $value)
			{
				$rc[$key . $separator . $vkey] = $value;
			}
		}
	}

	return $rc;
}

function wd_date_period($date)
{
	global $core;
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
	$language = $core->language;

	if (empty($relative[$language]))
	{
		$relative[$language] = $core->locale->conventions['dates']['fields']['day']['relative'];
	}

	if (isset($relative[$language][$diff]))
	{
		return $relative[$language][$diff];
	}
	else if ($diff > -6)
	{
		return ucfirst(wd_format_date($date_secs, 'EEEE'));
	}

	return wd_format_date($date);
}