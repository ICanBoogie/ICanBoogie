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

use ICanBoogie\PropertyNotDefined;
use ICanBoogie\PropertyNotWritable;

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
 * @property mixed $value The value of the property.
 * @property bool $has_value `true` if the value was retrieved, `false` otherwise.
 */
class PropertyEvent extends \ICanBoogie\Event
{
	/**
	 * The name of the property to retrieve.
	 *
	 * @var string
	 */
	public $property;

	/**
	 * `true` if the value was retrieved, `false` otherwise.
	 *
	 * @var boolean
	 */
	private $success;

	/**
	 * The value retrieved.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * The event is created with the type `property`.
	 *
	 * @param \ICanBoogie\Object $target
	 * @param string $property The property to retrieve.
	 * @param bool $success Reference to the success value.
	 */
	public function __construct($target, $property, &$success)
	{
		$this->property = $property;
		$this->success = &$success;

		parent::__construct($target, 'property');
	}

	public function __get($property)
	{
		switch ($property)
		{
			case 'value':

				return $this->value;

			case 'has_value':

				return $this->success;

			default:

				return parent::__get($property);
		}
	}

	public function __set($property, $value)
	{
		switch ($property)
		{
			case 'has_value':

				throw new PropertyNotWritable([ $property, $this ]);

			case 'value':

				$this->value = $value;
				$this->success = true;

				return;

			default:

				throw new PropertyNotDefined([ $property, $this ]);
		}
	}
}

namespace ICanBoogie\Prototype;

use ICanBoogie\Object\PropertyEvent;

/*
 * Patch `ICanBoogie\Prototype\last_chance_get`.
 */
Helpers::patch('last_chance_get', function($target, $property, &$success) {

	$success = false;
	$event = new PropertyEvent($target, $property, $success);

	return $event->has_value ? $event->value : null;

});

namespace ICanBoogie;

/*
 * Patch Active Record helpers
 */
if (class_exists('ICanBoogie\ActiveRecord\Helpers'))
{
	ActiveRecord\Helpers::patch('get_model', function($id) {

		return app()->models[$id];

	});
}

namespace ICanBoogie\HTTP;

/*
 * Patches the `get_dispatcher` helper to initialize the dispatcher with the operation and route
 * dispatchers.
 */
Helpers::patch('get_dispatcher', function() {

	static $dispatcher;

	if (!$dispatcher)
	{
		$dispatcher = new Dispatcher([

			'operation' => 'ICanBoogie\Operation\Dispatcher',
			'route' => 'ICanBoogie\Routing\Dispatcher'

		]);

		new Dispatcher\AlterEvent($dispatcher);
	}

	return $dispatcher;

});

namespace ICanBoogie\HTTP\Dispatcher;

use ICanBoogie\HTTP\Dispatcher;

/**
 * Event class for the `ICanBoogie\HTTP\Dispatcher::alter` event.
 *
 * Third parties may use this event to register additional dispatchers.
 */
class AlterEvent extends \ICanBoogie\Event
{
	/**
	 * The event is constructed with the type `alter`.
	 *
	 * @param Dispatcher $target
	 */
	public function __construct(Dispatcher $target)
	{
		parent::__construct($target, 'alter');
	}
}
