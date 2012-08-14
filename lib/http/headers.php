<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\HTTP;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14
 */
class Headers implements \ArrayAccess, \IteratorAggregate
{
	/**
	 * Headers array.
	 *
	 * @var array[string]mixed
	 */
	protected $headers = array();

	/**
	 * If the `REQUEST_URI` key is found in the headers they are considered coming from the
	 * super global $_SERVER array in which case the headers are filtered to only keep keys starting
	 * with the "HTTP_" prefix, and the keys are normalized e.g. "HTTP_CONTENT_TYPE" is
	 * converted to "Content-Type".
	 *
	 * @param array $headers The initial headers.
	 */
	public function __construct(array $headers=array())
	{
		if (isset($headers['REQUEST_URI']))
		{
			foreach ($headers as $key => $value)
			{
				if (strpos($key, 'HTTP_') !== 0)
				{
					continue;
				}

				$key = strtr(substr($key, 5), '_', '-');
				$key = mb_convert_case($key, MB_CASE_TITLE);
				$this[$key] = $value;
			}
		}
		else
		{
			foreach ($headers as $key => $value)
			{
				$this[$key] = $value;
			}
		}
	}

	/**
	 * Returns the headers as a string.
	 *
	 * @return string
	 */
	public function __toString()
	{
		$headers = $this->headers;

		if (!$headers)
		{
			return '';
		}

		$rc = '';
		ksort($headers);

		foreach ($headers as $name => $value)
		{
			$rc .= "$name: $value\r\n";
		}

		return $rc;
	}

	/**
	 * Checks if a header exists.
	 *
	 * @param mixed $offset
	 *
	 * @return boolean true if the header exists, false otherwise.
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($offset)
	{
		return isset($this->headers[(string) $offset]);
	}

	/**
	 * Returns a header.
	 *
	 * @param mixed $offset
	 *
	 * @return string|null The header value or null if it is not defined.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($offset)
	{
		return $this->offsetExists($offset) ? $this->headers[$offset] : null;
	}

	/**
	 * Sets a header.
	 *
	 * Note: Setting a header to `null` removes the header, just like unset() does.
	 *
	 * Date, Expires, Last-Modified
	 * ----------------------------
	 *
	 * The `Date`, `Expires` and `Last-Modified` headers can be provided as a Unix timestamp, a
	 * string or a \DateTime object.
	 *
	 * Content-Disposition
	 * -------------------
	 *
	 * If the `Content-Disposition` header is provided as an array the first value is used as
	 * disposition type and the second as filename. UTF-8 filenames are supported and handled
	 * according to specs.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($offset, $value)
	{
		if ($value === null)
		{
			unset($this[$offset]);

			return;
		}

		switch ($offset)
		{
			/*
			 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.5.1
			 * http://www.ietf.org/rfc/rfc1806.txt
			 * http://www.ietf.org/rfc/rfc2183.txt
			 * http://greenbytes.de/tech/webdav/draft-reschke-rfc2231-in-http-latest.html
			 * http://greenbytes.de/tech/tc2231/
			 */
			case 'Content-Disposition':
			{
				if (is_array($value))
				{
					list($disposition_type, $filename_param) = $value;

					if ($disposition_type != 'inline' && $disposition_type != 'attachment')
					{
						throw new \Exception('The disposition-type must be "inline" or "attachment", given: "' . $disposition_type . '"');
					}

					$value = $disposition_type . '; ' . self::format_param('filename', $filename_param);
				}
				else if ($value != 'inline' || $value != 'attachment')
				{
					throw new \Exception('The disposition-type must be "inline" or "attachment", given: "' . $value . '"');
				}
			}
			break;

			/*
			 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.18
			 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.21
			 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.29
			 */
			case 'Date':
			case 'Expires':
			case 'Last-Modified':
			{
				$value = self::format_time($value);
			}
			break;
		}

		$this->headers[$offset] = $value;
	}

	/**
	 * Removes a header.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($offset)
	{
		unset($this->headers[$offset]);
	}

	/**
	 * Returns an iterator for the headers.
	 *
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->headers);
	}

	/**
	 * Formats a datetime into a date string suitable for the `Date`, `Last-Modified` or
	 * `Expires` headers.
	 *
	 * @param int|string|\DateTime $datetime The parameter can be provided as a Unix timestamp, a
	 * string or a DataTime object. If the parameter is provided as a Unix timestamp or a
	 * string a DateTime object is created with its value.
	 *
	 * @return string
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3.1
	 */
	static public function format_time($datetime)
	{
		if (!($datetime instanceof \DateTime))
		{
			$datetime = new \DateTime(is_numeric($datetime) ? '@' . $datetime : $datetime);
		}

		$datetime->setTimezone(new \DateTimeZone('UTC'));

		return str_replace(' +0000', ' GMT', $datetime->format(\DateTime::RFC1123));
	}

	/**
	 * Formats a parameter and its value.
	 *
	 * If the value is encoded using UTF-8 the parameter is added twice: once with a normalized
	 * value, and another with an escaped value.
	 *
	 * @param string $param The parameter.
	 * @param string $value The value of the parameter.
	 *
	 * @return string
	 *
	 * @see http://greenbytes.de/tech/tc2231/
	 */
	static public function format_param($param, $value)
	{
		if (mb_detect_encoding($value, 'ASCII, UTF-8', true) === 'UTF-8')
		{
			return $param . '="' . \ICanBoogie\remove_accents($value) . '"' . "; $param*=UTF-8''" . rawurlencode($value);
		}

		return $param . '="' . $value . '"';
	}
}