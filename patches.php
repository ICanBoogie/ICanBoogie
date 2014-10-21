<?php

namespace ICanBoogie\Object;

/**
 * Event class for the `ICanBoogie\Object::property` event.
 *
 * The `ICanBoogie\Object::property` event is fired when no getter was found in a class or
 * prototype to obtain the value of a property.
 *
 * Hooks can be attached to that event to provide the value of the property. Should they be able
 * to provide the value, they must create the `value` property within the event object. Thus, even
 * `null` is considered a valid result.
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
	 * The event is created with the type `property`.
	 *
	 * @param \ICanBoogie\Object $target
	 * @param array $payload
	 */
	public function __construct($target, array $payload)
	{
		parent::__construct($target, 'property', $payload);
	}
}

namespace ICanBoogie;

/*
 * Patch Prototype helpers
 */
Prototype\Helpers::patch('last_chance_get', function($target, $property, &$success)
{
	try
	{
		app();
	}
	catch (CoreNotInstantiated $e) {}

	$event = new Object\PropertyEvent($target, [ 'property' => $property ]);

	#
	# The operation is considered a success if the `value` property exists in the event
	# object. Thus, even a `null` value is considered a success.
	#

	if (!property_exists($event, 'value'))
	{
		return;
	}

	$success = true;

	return $event->value;
});

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
 * Third parties may use this event to register additionnal dispatchers.
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