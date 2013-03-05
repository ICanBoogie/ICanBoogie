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
	public function __construct(\ICanBoogie\Object $target, array $payload)
	{
		parent::__construct($target, 'property', $payload);
	}
}

namespace ICanBoogie;

/*
 * Patch Prototype helpers
 */
Prototype\Helpers::patch('last_chance_get', function (Object $target, $property, &$success)
{
	global $core;

	if (empty($core))
	{
		return;
	}

	$event = new Object\PropertyEvent($target, array('property' => $property));

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
ActiveRecord\Helpers::patch('get_model', function($id) {

	return Core::get()->models[$id];

});

namespace ICanBoogie\HTTP;

/*
 * Patches the `get_dispatcher` helper to initialize the dispatcher with the operation and route
 * dispatchers.
 */
Helpers::patch('get_dispatcher', function() {

	static $dispatcher;

	if (!$dispatcher)
	{
		$dispatchers = array
		(
			'operation' => 'ICanBoogie\OperationDispatcher',
			'route' => 'ICanBoogie\Routing\Dispatcher'
		);

		new Dispatcher\CollectEvent(new Dispatcher(), $dispatchers);

		$dispatcher = new Dispatcher($dispatchers);
	}

	return $dispatcher;

});

namespace ICanBoogie\HTTP\Dispatcher;

use ICanBoogie\HTTP\Dispatcher;

/**
 * Event class for the `ICanBoogie\HTTP\Dispatcher::collect` event.
 *
 * Third parties may use this event to register additionnal dispatchers.
 */
class CollectEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the dispatchers array.
	 *
	 * @var array[string]callable
	 */
	public $dispatchers;

	/**
	 * The event is constructed with the type `collect`.
	 *
	 * @param Dispatcher $target
	 * @param array $payload
	 */
	public function __construct(Dispatcher $target, array &$dispatchers)
	{
		$this->dispatchers = &$dispatchers;

		parent::__construct($target, 'collect');
	}
}