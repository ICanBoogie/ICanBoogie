<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Autoconfig;

use Composer\Script\Event;

class Hooks
{
	/**
	 * Generate the _autoconfig_ file on 'autoload_dump' Composer event.
	 *
	 * @param Event $event
	 */
	static public function on_autoload_dump(Event $event)
	{
		$composer = $event->getComposer();
		$package = $composer->getPackage();
		$generator = $composer->getAutoloadGenerator();
		$packages = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
		$packageMap = $generator->buildPackageMap($composer->getInstallationManager(), $package, $packages);
		$sorted = FakeAutoloadGenerator::sort_package_map($generator, $packageMap);

		$vendor_dir = $composer->getConfig()->get('vendor-dir');
		$destination = realpath($vendor_dir) . "/icanboogie/autoconfig.php";
		$config = new Config($sorted, $destination);
		$config();
	}

	/**
	 * Adds the "config" directories found in the app paths to `config-path`.
	 *
	 * @param array $autoconfig
	 */
	static public function filter_autoconfig(array &$autoconfig)
	{
		foreach ($autoconfig['app-paths'] as $directory)
		{
			if (file_exists($directory . 'config'))
			{
				$autoconfig['config-path'][$directory . 'config'] = 20;
			}
		}
	}
}
