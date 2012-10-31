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
	 * @param array $properties
	 */
	public function __construct(\ICanBoogie\Object $target, array $properties)
	{
		parent::__construct($target, 'property', $properties);
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