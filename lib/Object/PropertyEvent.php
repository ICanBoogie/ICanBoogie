<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Object;

use ICanBoogie\Event;

/**
 * Event class for the `ICanBoogie\Object::property` event.
 *
 * The `ICanBoogie\Object::property` event is fired when no getter was found in a class or
 * prototype to obtain the value of a property.
 *
 * Hooks can be attached to that event to provide the value of the property. Should they be able
 * to provide the value, they must create the `value` property within the event object. Thus, even
 * `null` is considered a valid result.
 *
 * @property-read string $property The property to retrieve.
 * @property mixed $value The value of the property.
 * @property-read bool $has_value `true` if the value was retrieved, `false` otherwise.
 */
class PropertyEvent extends Event
{
	/**
	 * The name of the property to retrieve.
	 *
	 * @var string
	 */
	private $property;

	protected function get_property()
	{
		return $this->property;
	}

	/**
	 * `true` if the value was retrieved, `false` otherwise.
	 *
	 * @var boolean
	 */
	private $success;

	protected function get_has_value()
	{
		return $this->success;
	}

	/**
	 * The value retrieved.
	 *
	 * @var mixed
	 */
	private $value;

	protected function get_value()
	{
		return $this->value;
	}

	protected function set_value($value)
	{
		$this->success = true;
		$this->value = $value;
	}

	/**
	 * The event is created with the type `property`.
	 *
	 * @param object $target
	 * @param string $property The property to retrieve.
	 * @param bool $success Reference to the success value.
	 */
	public function __construct($target, $property, &$success)
	{
		$this->property = $property;
		$this->success = &$success;
		$success = false;

		parent::__construct($target, 'property');
	}
}
