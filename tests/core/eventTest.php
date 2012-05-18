<?php

namespace ICanBoogie\Tests\Core\Event;

use ICanBoogie\Event;
use ICanBoogie\Events;

class A
{
	public function __invoke(array $values)
	{
		if (!$this->validate($values))
		{
			throw new \Exception("Values validation failed.");
		}

		new BeforeProcessEvent($this, array('values' => &$values));

		return $this->process($values);
	}

	protected function validate(array $values)
	{
		$valid = false;

		new ValidateEvent($this, array('values' => $values, 'valid' => &$valid));

		return $valid;
	}

	protected function process(array $values)
	{
		new ProcessEvent($this, array('values' => &$values));

		return $values;
	}
}

class B extends A
{
	protected function process(array $values)
	{
		return parent::process($values + array('five' => 5));
	}
}

/**
 * Event class for the `Test\A::validate` event.
 */
class ValidateEvent extends Event
{
	public $values;

	public $valid;

	public function __construct(A $target, array $properties)
	{
		parent::__construct($target, 'validate', $properties);
	}
}

/**
 * Event class for the `Test\A::process:before` event.
 */
class BeforeProcessEvent extends Event
{
	public $values;

	public function __construct(A $target, array $properties)
	{
		parent::__construct($target, 'process:before', $properties);
	}
}

/**
 * Event class for the `Test\A::process` event.
 */
class ProcessEvent extends Event
{
	public $values;

	public function __construct(A $target, array $properties)
	{
		parent::__construct($target, 'process', $properties);
	}
}

class EventTest extends \PHPUnit_Framework_TestCase
{
	public function testEventCallbacks()
	{
		/*
		 * The A::validate() method would return false if the following callback wasn't called.
		 */
		Events::attach(__NAMESPACE__ . '\A::validate', function(ValidateEvent $event, A $target) {

			$event->valid = true;
		});

		/*
		 * We add "three" to the values of A instances before they are processed.
		 */
		Events::attach(__NAMESPACE__ . '\A::process:before', function(BeforeProcessEvent $event, A $target) {

			$event->values['three'] = 3;
		});

		/*
		 * This callback is called before any callback set on the A class, because we want "four" to be
		 * after "three", which is added by the callback above, we use the _chain_ feature of the event.
		 *
		 * Callbacks pushed by the chain() method are executed after the even chain is processed.
		 */
		Events::attach(__NAMESPACE__ . '\B::process:before', function(BeforeProcessEvent $event, B $target) {

			$event->chain(function($event) {

				$event->values['four'] = 4;
			});
		});

		/*
		 * 10 is added to all processed values of A instances.
		 */
		Events::attach(__NAMESPACE__ . '\A::process', function(ProcessEvent $event, A $target) {

			array_walk($event->values, function(&$v) {

				$v += 10;
			});
		});

		/*
		 * We want processed values to be mutiplied by 10 for B instances, because 10 is already added to
		 * values of A instances we need to stop the event from propagating.
		 *
		 * The stop() method of the event breaks the event chain, so our callback will be the last
		 * called in the chain.
		 */
		Events::attach(__NAMESPACE__ . '\B::process', function(ProcessEvent $event, B $target) {

			array_walk($event->values, function(&$v) {

				$v *= 10;
			});

			$event->stop();
		});

		$initial_array = array('one' => 1, 'two' => 2);

		$a = new A;
		$b = new B;

		$a_processed = $a($initial_array);
		$b_processed = $b($initial_array);

		$this->assertEquals(array('one' => 11, 'two' => 12, 'three' => 13), $a_processed);
		$this->assertEquals('one,two,three', implode(',', array_keys($a_processed)));

		$this->assertEquals(array('one' => 10, 'two' => 20, 'three' => 30, 'four' => 40, 'five' => 50), $b_processed);
		$this->assertEquals('one,two,three,four,five', implode(',', array_keys($b_processed)));
	}
}
