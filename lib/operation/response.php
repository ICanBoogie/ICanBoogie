<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Operation;

use ICanBoogie\Errors;

/**
 * @property-read \ICanBoogie\Errors $errors
 */
class Response extends \ICanBoogie\HTTP\Response implements \ArrayAccess
{
	public $rc;
	public $success;

	protected $metas=array();

	public function __invoke()
	{
		if ($this->body === null)
		{
			$success = $this->success;

			if ($success === null)
			{
				$success = \ICanBoogie\Debug::fetch_messages('success');

				if ($success)
				{
					$success = implode("\n", $success); // FIXME-20110923: we shouldn't use the log anymore !
				}
				else
				{
					$success = null;
				}
			}
			else if (is_array($success))
			{
				$success = call_user_func_array('t', $success);
			}

			$errors = null;

			if (isset($this->errors) && count($this->errors))
			{
				$errors = array();

				foreach ($this->errors as $identifier => $message)
				{
					if (!$identifier)
					{
						$identifier = '_base';
					}

					if (isset($errors[$identifier]))
					{
						$errors[$identifier] .= '; ' . $message;
					}
					else
					{
						$errors[$identifier] = $message;
					}
				}
			}

			$rc = $this->rc;

			$body_data = array
			(
				'rc' => $rc,
				'success' => $success,
				'errors' => $errors
			)

			+ $this->metas;

			if ($success === null)
			{
				unset($body_data['success']);
			}

			if ($errors === null)
			{
				unset($body_data['errors']);
			}

			if (!$this->is_successful)
			{
				unset($body_data['rc']);
			}

			/*
			 * If a location is set on the request it is remove and added to the result message.
			 * This is because if we use XHR to get the result we don't want that result to go
			 * avail because the operation usually change the location.
			 *
			 * TODO-20110924: for XHR instead of JSON/XML.
			 */
			if ($this->location)
			{
				$body_data['location'] = $this->location;

				$this->location = null;
			}

			$body = $rc;

			if ($this->content_type == 'application/json')
			{
				$body = json_encode($body_data);
				$this->content_length = null;
			}
			else if ($this->content_type == 'application/xml')
			{
				$body = array_to_xml($body_data, 'response');
				$this->content_length = null;
			}

			if ($this->content_length === null && is_string($body))
			{
				$this->volatile_set_content_length(strlen($body));
			}

			$this->body = $body;
		}

		return parent::__invoke();
	}

	/*
	 * TODO-20110923: we used to return *all* the fields of the response, we can't do this anymore
	 * because most of this stuff belongs to the response object. We need a mean to add
	 * additional properties, and maybe we could use the response as an array for this purpose:
	 *
	 * Example:
	 *
	 * $response->rc = true;
	 * $response['widget'] = (string) new Button('madonna');
	 *
	 * This might be better than $response->additional_results->widget = ...;
	 *
	 * Or we could let that behind us and force everything in the `rc`:
	 *
	 * rc: {
	 *
	 *     widget: '<div class="widget-pop-node">...</div>',
	 *     assets: {
	 *
	 *         css: [...],
	 *         js: [...]
	 *     }
	 * }
	 *
	 * We could also drop 'rc' because it was used to check if the operation was
	 * successful (before we handle HTTP status correctly), we might not need it anymore.
	 *
	 * If the operation returns anything but an array, it is converted into an array with the 'rc'
	 * key and the value, 'success' and 'errors' are added if needed. This only apply to XHR
	 * request !!
	 *
	 */

	/**
	 * Checks if a meta exists.
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($offset)
	{
		return isset($this->metas[$offset]);
	}

	/**
	 * Returns a meta or null if it is not defined.
	 *
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($offset)
	{
		return $this->offsetExists($offset) ? $this->metas[$offset] : null;
	}

	/**
	 * Sets a meta.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($offset, $value)
	{
		$this->metas[$offset] = $value;
	}

	/**
	 * Unsets a meta.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($offset)
	{
		unset($this->metas[$offset]);
	}

	protected function get_errors()
	{
		return new Errors();
	}
}

function array_to_xml($array, $parent='root', $encoding='utf-8', $nest=1)
{
	$rc = '';

	if ($nest == 1)
	{
		#
		# first level, time to write the XML header and open the root markup
		#

		$rc .= '<?xml version="1.0" encoding="' . $encoding . '"?>' . PHP_EOL;
		$rc .= '<' . $parent . '>' . PHP_EOL;
	}

	$tab = str_repeat("\t", $nest);

	if (substr($parent, -3, 3) == 'ies')
	{
		$collection = substr($parent, 0, -3) . 'y';
	}
	else if (substr($parent, -2, 2) == 'es')
	{
		$collection = substr($parent, 0, -2);
	}
	else if (substr($parent, -1, 1) == 's')
	{
		$collection = substr($parent, 0, -1);
	}
	else
	{
		$collection = 'entry';
	}

	foreach ($array as $key => $value)
	{
		if (is_numeric($key))
		{
			$key = $collection;
		}

		if (is_array($value) || is_object($value))
		{
			$rc .= $tab . '<' . $key . '>' . PHP_EOL;
			$rc .= wd_array_to_xml((array) $value, $key, $encoding, $nest + 1);
			$rc .= $tab . '</' . $key . '>' . PHP_EOL;

			continue;
		}

		#
		# if we find special chars, we put the value into a CDATA section
		#

		if (strpos($value, '<') !== false || strpos($value, '>') !== false || strpos($value, '&') !== false)
		{
			$value = '<![CDATA[' . $value . ']]>';
		}

		$rc .= $tab . '<' . $key . '>' . $value . '</' . $key . '>' . PHP_EOL;
	}

	if ($nest == 1)
	{
		#
		# first level, time to close the root markup
		#

		$rc .= '</' . $parent . '>';
	}

	return $rc;
}