<?php

namespace ICanBoogie;

use ICanBoogie\AppAccessorTest\UseCase;

class AppAccessorTest extends \PHPUnit\Framework\TestCase
{
	public function test_get()
	{
		$use_case = new UseCase;

		$this->assertSame(app(), $use_case->app);
	}
}
