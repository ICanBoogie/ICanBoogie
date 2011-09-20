<?php

namespace ICanBoogie\HTTP;

class Headers implements \ArrayAccess
{
	protected $headers=array();

	/**
	 * @param offset
	 */
	public function offsetExists($offset)
	{
		return isset($this->headers[(string) $offset]);
	}

	/**
	 * @param offset
	 */
	public function offsetGet($offset)
	{
		return isset($this->headers[(string) $offset]) ? $this->headers[(string) $offset] : null;
	}

	/**
	 * @param offset
	 * @param value
	 */
	public function offsetSet($offset, $value)
	{
		$this->headers[(string) $offset] = $value;
	}

	/**
	 * @param offset
	 */
	public function offsetUnset($offset)
	{
		unset($this->headers[(string) $offset]);
	}

	public function __construct(array $env=array())
	{
		$headers = array();

		if (isset($env['REQUEST_URI']))
		{
			foreach ($env as $key => $value)
			{
				if (strpos($key, 'HTTP_') !== 0)
				{
					continue;
				}

				$key = strtr(substr($key, 5), '_', '-');
				$key = mb_convert_case($key, MB_CASE_TITLE);
				$headers[$key] = $value;
			}
		}
		else
		{
			$headers = $env;
		}

		$this->headers = $headers;
	}

	/**
	* Returns the headers as a string.
	*
	* @return string The headers
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
}