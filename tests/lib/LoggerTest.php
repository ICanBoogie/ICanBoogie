<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

class LoggerTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $session;

	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $app;

	public function setUp()
	{
		$this->session = $this
			->getMockBuilder(Session::class)
			->disableOriginalConstructor()
			->setMethods([ '__set' ])
			->getMock();

		$this->app = $this
			->getMockBuilder(Application::class)
			->disableOriginalConstructor()
			->setMethods([ 'get_session' ])
			->getMock();

		$this->app
			->expects($this->any())
			->method('get_session')
			->willReturn($this->session);
	}

	public function test_get_logger()
	{
		$app = $this->app;

		/* @var $app Application */

		$logger = Logger::get_logger($app);
		$this->assertInstanceOf(Logger::class, $logger);
		$this->assertSame($logger, Logger::get_logger($app));
	}

	public function test_log()
	{
		$arg = uniqid();
		$message = "message: :id";
		$formatted_message = format($message, [ 'id' => $arg ]);

		$session = $this->session;
		$session
			->expects($this->any())
			->method('__set')
			->with($message);

		$app = $this->app;

		/* @var $app Application */

		$logger = new Logger($app);
		$logger->success($message, [ 'id' => $arg ]);
		$this->assertEquals([ $formatted_message ], $logger->fetch_messages(LogLevel::SUCCESS));
	}

	public function test_log_many_messages()
	{
		$max = Logger::MAX_MESSAGES;
		$count = $max * 2;

		$session = $this->session;
		$session
			->expects($this->any())
			->method('__set');

		$app = $this->app;

		/* @var $app Application */

		$logger = new Logger($app);
		$this->assertEmpty($logger->fetch_messages(LogLevel::SUCCESS));

		$last_message = null;

		for ($i = 0 ; $i < $count ; $i++)
		{
			$last_message = uniqid();
			$logger->success($last_message);
		}

		$messages = $logger->fetch_messages(LogLevel::SUCCESS);
		$this->assertCount($max, $messages);
		$this->assertSame($last_message, end($messages));
	}
}
