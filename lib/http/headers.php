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

use ICanBoogie\PropertyNotReadable;
use ICanBoogie\PropertyNotWritable;

/**
 * HTTP Header Field Definitions.
 *
 * Instances of this class are used to collect and manipulate HTTP header field definitions.
 * Header field instances are used to handle the definition of complex header fields such as
 * `Content-Type` and `Cache-Control`. For instance a {@link Header\CacheControl} instance
 * is used to handle the various possibilities of the `Cache-Control` header field.
 *
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
 */
class Headers implements \ArrayAccess, \IteratorAggregate
{
	/**
	 * Header fields.
	 *
	 * @var array[string]mixed
	 */
	protected $fields = array();

	/**
	 * If the `REQUEST_URI` key is found in the header fields they are considered coming from the
	 * super global `$_SERVER` array in which case they are filtered to keep only keys
	 * starting with the `HTTP_` prefix. Also, header field names are normalized. For instance,
	 * `HTTP_CONTENT_TYPE` becomes `Content-Type`.
	 *
	 * @param array $headers The initial headers.
	 */
	public function __construct(array $fields=array())
	{
		if (isset($fields['REQUEST_URI']))
		{
			foreach ($fields as $field => $value)
			{
				if (strpos($field, 'HTTP_') !== 0)
				{
					continue;
				}

				$field = strtr(substr($field, 5), '_', '-');
				$field = mb_convert_case($field, MB_CASE_TITLE);
				$this[$field] = $value;
			}
		}
		else
		{
			foreach ($fields as $field => $value)
			{
				$this[$field] = $value;
			}
		}
	}

	/**
	 * Returns the header as a string.
	 *
	 * Header fields with empty string values are discarted.
	 *
	 * @return string
	 */
	public function __toString()
	{
		$header = '';

		foreach ($this->fields as $field => $value)
		{
			$value = (string) $value;

			if ($value === '')
			{
				continue;
			}

			$header .= "$field: $value\r\n";
		}

		return $header;
	}

	/**
	 * Sends header fields using the {@link header()} function.
	 *
	 * Header fields with empty string values are discarted.
	 */
	public function __invoke()
	{
		foreach ($this->fields as $field => $value)
		{
			$value = (string) $value;

			if ($value === '')
			{
				continue;
			}

			header("$field: $value");
		}
	}

	/**
	 * Checks if a header field exists.
	 *
	 * @param mixed $field
	 *
	 * @return boolean
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($field)
	{
		return isset($this->fields[(string) $field]);
	}

	/**
	 * Returns a header.
	 *
	 * @param mixed $field
	 *
	 * @return string|null The header field value or null if it is not defined.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($field)
	{
		switch ($field)
		{
			case 'Cache-Control':
			{
				if (empty($this->fields[$field]))
				{
					$this->fields[$field] = new Headers\CacheControl();
				}

				return $this->fields[$field];
			}
			break;

			case 'Content-Type':
			{
				if (empty($this->fields[$field]))
				{
					$this->fields[$field] = new Headers\ContentType();
				}

				return $this->fields[$field];
			}
			break;
		}

		return $this->offsetExists($field) ? $this->fields[$field] : null;
	}

	/**
	 * Sets a header field.
	 *
	 * Note: Setting a header field to `null` removes it, just like unset() would.
	 *
	 * Date, Expires, Last-Modified
	 * ----------------------------
	 *
	 * The `Date`, `Expires` and `Last-Modified` header fields can be provided as a Unix
	 * timestamp, a string or a {@link \DateTime} object.
	 *
	 * Content-Disposition
	 * -------------------
	 *
	 * If the `Content-Disposition` header field is provided as an array the first value is used
	 * as disposition type and the second as filename. UTF-8 filenames are supported and handled
	 * according to specs.
	 *
	 * Cache-Control and Content-Type
	 * ------------------------------
	 *
	 * Instances of the {@link Headers\CacheControl} and {@link Headers\ContentType} classes are
	 * used to handle the values of the `Cache-Control` and `Content-Type` header fields.
	 *
	 * @param string $field The header field to set.
	 * @param mixed $value The value of the header field.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($field, $value)
	{
		if ($value === null)
		{
			unset($this[$field]);

			return;
		}

		switch ($field)
		{
			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.6
			case 'Age':
			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.18
			case 'Date':
			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.21
			case 'Expires':
			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.25
			case 'If-Modified-Since':
			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.28
			case 'If-Unmodified-Since':
			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.29
			case 'Last-Modified':
			{
				$value = new Headers\DateTime($value);
			}
			break;

			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9
			case 'Cache-Control':
			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.17
			case 'Content-Type':
			{
				$this[$field]->set($value);
			}
			return;

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

			# http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.37
			case 'Retry-After':
			{
				$value = is_numeric($value) ? $value : new Headers\DateTime($value);
			}
			break;
		}

		$this->fields[$field] = $value;
	}

	/**
	 * Removes a header field.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($field)
	{
		unset($this->fields[$field]);
	}

	/**
	 * Returns an iterator for the header fields.
	 *
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->fields);
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

namespace ICanBoogie\HTTP\Headers;

use ICanBoogie\Exception;

/**
 * A date time object that renders into a string formatted for HTTP header fields.
 *
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3.1
 */
class DateTime extends \DateTime
{
	private static $utc_time_zone;

	private static function get_utc_time_zone()
	{
		$utc_time_zone = self::$utc_time_zone;

		if (!$utc_time_zone)
		{
			self::$utc_time_zone = $utc_time_zone = new \DateTimeZone('UTC');
		}

		return $utc_time_zone;
	}

	/**
	 * Returns a new {@link DateTime} object.
	 *
	 * @param string|int|\DateTime $time If time is provided as a numeric value it is used as
	 * "@{$time}" and the time zone is set to UTC.
	 * @param \DateTimeZone $timezone A {@link \DateTimeZone} object representing the desired time zone.
	 */
	public function __construct($time='now', \DateTimeZone $timezone=null)
	{
		if ($time instanceof \DateTime)
		{
			$time = $time->getTimestamp();
		}

		if (is_numeric($time))
		{
			$time = '@' . $time;
			$timezone = self::get_utc_time_zone();
		}

		if (!$timezone)
		{
			$timezone = self::get_utc_time_zone();
		}

		parent::__construct($time, $timezone);

		$this->setTimezone(self::get_utc_time_zone());
	}

	public function __get($property)
	{
		switch ($property)
		{
			case 'last_errors': return $this->getLastErrors();
			case 'offset': return $this->getOffset();
			case 'timestamp': return $this->getTimestamp();
			case 'timezone': return $this->getTimezone();
		}

		throw new PropertyNotReadable(array($property, $this));
	}

	public function __set($property, $value)
	{
		switch ($property)
		{
			case 'date': call_user_func_array(array($this, 'setDate'), $value);
			case 'iso_date': call_user_func_array(array($this, 'setISODate'), $value);
			case 'time': call_user_func_array(array($this, 'setTime'), $value);
			case 'timestamp': $this->setTimestamp($value);
			case 'timezone': $this->setTimezone($value);
			default: throw new PropertyNotWritable(array($property, $this));
		}
	}

	public function __toString()
	{
		return $this->format('D, d M Y H:i:s') . ' GMT';
	}
}

/**
 * Representation of the `Content-Type` header field.
 */
class ContentType
{
	static public function parse($content_type)
	{
		preg_match('#^([^;]+)(;\s+charset=([^\s]+))?$#', $content_type, $matches);

		return array(isset($matches[1]) ? $matches[1] : null, isset($matches[3]) ? $matches[3] : null);
	}

	static public function format($content_type)
	{
		$charset = null;

		if (is_array($content_type))
		{
			list($content_type, $charset) = $content_type + array(1 => null);
		}

		if ($charset)
		{
			if (!$content_type)
			{
				$content_type = 'text/html';
			}

			$content_type .= '; charset=' . $charset;
		}

		return $content_type;
	}

	/**
	 * The type/subtype part of the content type.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The charset part of the content type.
	 *
	 * @var null|string
	 */
	public $charset;

	/**
	 * If defined, the object is initialized with the content type.
	 *
	 * @param string $content_type
	 */
	public function __construct($content_type=null)
	{
		if ($content_type)
		{
			$this->set($content_type);
		}
	}

	/**
	 * Sets the content type, updating the properties of the object.
	 *
	 * @param string|array $content_type
	 */
	public function set($content_type)
	{
		if (is_array($content_type))
		{
			list($type, $charset) = $content_type + array(1 => null);
		}
		else
		{
			list($type, $charset) = static::parse($content_type);
		}

		$this->type = $type;
		$this->charset = $charset;
	}

	/**
	 * Returns a string representation of the object.
	 *
	 * @return string
	 */
	public function __toString()
	{
		$content_type = $this->type;

		if (!$content_type)
		{
			return '';
		}

		if ($this->charset)
		{
			$content_type .= '; charset=' . $this->charset;
		}

		return $content_type;
	}
}

/**
 * Representation of the `Cache-Control` header field.
 */
class CacheControl
{
	static public $cacheable_values = array
	(
		'private',
		'public',
		'no-cache'
	);

	static public $booleans = array
	(
		'no-store',
		'no-transform',
		'only-if-cached',
		'must-revalidate',
		'proxy-revalidate'
	);

	static public $placeholder = array
	(
		'cacheable'
	);

	static private $default_values = array();

	static private function get_default_values()
	{
		$class = get_called_class();

		if (isset(self::$default_values[$class]))
		{
			return self::$default_values[$class];
		}

		$reflection = new \ReflectionClass($class);
		$default_values = array();

		foreach ($reflection->getDefaultProperties() as $property => $default_value)
		{
			$property_reflection = new \ReflectionProperty($class, $property);

			if ($property_reflection->isStatic() || !$property_reflection->isPublic())
			{
				continue;
			}

			$default_values[$property] = $default_value;
		}

		return self::$default_values[$class] = $default_values;
	}

	/**
	 * Wheter the request/response is cacheable. The following properties are supported: `public`,
	 * `private` and `no-cache`. The variable may be empty in which case the cacheability of the
	 * request/response is unspecified.
	 *
	 * Scope: request, response.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.1
	 *
	 * @var string
	 */
	public $cacheable;

	/**
	 * Wheter the request/response is can be stored.
	 *
	 * Scope: request, response.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.2
	 *
	 * @var bool
	 */
	public $no_store = false;

	/**
	 * Indicates that the client is willing to accept a response whose age is no greater than the
	 * specified time in seconds. Unless max- stale directive is also included, the client is not
	 * willing to accept a stale response.
	 *
	 * Scope: request.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.3
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.4
	 *
	 * @var int
	 */
	public $max_age;

	/**
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.3
	 *
	 * @var int
	 */
	public $s_maxage;

	/**
	 * Indicates that the client is willing to accept a response that has exceeded its expiration
	 * time. If max-stale is assigned a value, then the client is willing to accept a response
	 * that has exceeded its expiration time by no more than the specified number of seconds. If
	 * no value is assigned to max-stale, then the client is willing to accept a stale response
	 * of any age.
	 *
	 * Scope: request.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.3
	 *
	 * @var string
	 */
	public $max_stale;

	/**
	 * Indicates that the client is willing to accept a response whose freshness lifetime is no
	 * less than its current age plus the specified time in seconds. That is, the client wants a
	 * response that will still be fresh for at least the specified number of seconds.
	 *
	 * Scope: request.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.3
	 *
	 * @var int
	 */
	public $min_fresh;

	/**
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.5
	 *
	 * Scope: request, response.
	 *
	 * @var bool
	 */
	public $no_transform = false;

	/**
	 * Scope: request.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.4
	 *
	 * @var bool
	 */
	public $only_if_cached = false;

	/**
	 * Scope: response.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.4
	 *
	 * @var bool
	 */
	public $must_revalidate = false;

	/**
	 * Scope: response.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.4
	 *
	 * @var bool
	 */
	public $proxy_revalidate = false;

	/**
	 * Scope: request, response.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.6
	 *
	 * @var string
	 */
	public $extensions = array();

	/**
	 * If they are defined, the object is initialized with the cache directives.
	 *
	 * @param string $cache_directive Cache directives.
	 */
	public function __construct($cache_directives=null)
	{
		if ($cache_directives)
		{
			$this->set($cache_directives);
		}
	}

	/**
	 * Returns cache directives.
	 *
	 * @return string
	 */
	public function __toString()
	{
		$cache_directive = '';

		foreach (get_object_vars($this) as $directive => $value)
		{
			$directive = strtr($directive, '_', '-');

			if (in_array($directive, self::$booleans))
			{
				if (!$value)
				{
					continue;
				}

				$cache_directive .= ', ' . $directive;
			}
			else if (in_array($directive, self::$placeholder))
			{
				if (!$value)
				{
					continue;
				}

				$cache_directive .= ', ' . $value;
			}
			else if (is_array($value))
			{
				// TODO: 20120831: extentions

				continue;
			}
			else if ($value !== null && $value !== false)
			{
				$cache_directive .= ", $directive=$value";
			}
		}

		return $cache_directive ? substr($cache_directive, 2) : '';
	}

	/**
	 * Sets the cache directives, updating the properties of the object.
	 *
	 * Unknown directives are stashed in the {@link $extensions} property.
	 *
	 * @param string $cache_directive
	 */
	public function set($cache_directive)
	{
		$directives = explode(',', $cache_directive);
		$directives = array_map('trim', $directives);

		$properties = static::get_default_values();

		foreach ($directives as $value)
		{
			if (in_array($value, self::$booleans))
			{
				$property = strtr($value, '-', '_');
				$properties[$property] = true;
			}
			if (in_array($value, self::$cacheable_values))
			{
				$properties['cacheable'] = $value;
			}
			else if (preg_match('#^([^=]+)=(.+)$#', $value, $matches))
			{
				list(, $directive, $value) = $matches;

				$property = strtr($directive, '-', '_');

				if (is_numeric($value))
				{
					$value = 0 + $value;
				}

				if (!array_key_exists($property, $properties))
				{
					$this->extensions[$property] = $value;

					continue;
				}

				$properties[$property] = $value;
			}
		}

		foreach ($properties as $property => $value)
		{
			$this->$property = $value;
		}
	}
}