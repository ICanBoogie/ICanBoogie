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

class AppConfigTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * @expectedException \LogicException
	 */
	public function testShouldThrowExceptionOnInvalidRepository()
	{
		AppConfig::synthesize([ [

			AppConfig::REPOSITORY => __DIR__ . 'AppConfigTest.php/' . uniqid()

		] ]);
	}

	public function testInterpolateRepository()
	{
		$repository = __DIR__;

		$config = AppConfig::synthesize([ [

			AppConfig::REPOSITORY => $repository,
			AppConfig::REPOSITORY_CACHE => "{repository}/cache",
			AppConfig::REPOSITORY_CACHE_CONFIGS => "{repository}/cache/configs",
			AppConfig::REPOSITORY_FILES => "{repository}/files",
			AppConfig::REPOSITORY_TMP => "{repository}/tmp",
			AppConfig::REPOSITORY_VARS => "{repository}/vars",

		] ]);

		$this->assertSame([

			AppConfig::REPOSITORY => "$repository/",
			AppConfig::REPOSITORY_CACHE => "$repository/cache/",
			AppConfig::REPOSITORY_CACHE_CONFIGS => "$repository/cache/configs/",
			AppConfig::REPOSITORY_FILES => "$repository/files/",
			AppConfig::REPOSITORY_TMP => "$repository/tmp/",
			AppConfig::REPOSITORY_VARS => "$repository/vars/",

		], $config);
	}
}
