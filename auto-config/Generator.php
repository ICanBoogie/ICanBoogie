<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\AutoConfig;

use Composer\Script\Event;

class FakeAutoloadGenerator extends \Composer\Autoload\AutoloadGenerator
{
	static public function sort_package_map(\Composer\Autoload\AutoloadGenerator $generator, $packageMap)
	{
		return $generator->sortPackageMap($packageMap);
	}
}

class Generator
{
	static public function on_autoload_dump(Event $event)
	{
		$composer = $event->getComposer();
		$package = $composer->getPackage();
		$generator = $composer->getAutoloadGenerator();
		$packages = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
		$packageMap = $generator->buildPackageMap($composer->getInstallationManager(), $package, $packages);
		$sorted = FakeAutoloadGenerator::sort_package_map($generator, $packageMap);

		$vendor_dir = $composer->getConfig()->get('vendor-dir');
		$destination = realpath($vendor_dir) . "/icanboogie/auto-config.php";
		$config = new Config($sorted, $destination);
		$config();
	}
}