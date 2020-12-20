<?php

namespace ICanBoogie;

class SessionWithEventTest extends \PHPUnit\Framework\TestCase
{
	public function test_for_app()
	{
		$session = SessionWithEvent::for_app(app());
		$this->assertSame($session, SessionWithEvent::for_app(app()));
		$this->assertSame($session, app()->session);
	}
}
