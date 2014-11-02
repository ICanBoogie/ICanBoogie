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

use ICanBoogie\resolve_app_paths;
class Hooks
{
	/**
	 * Generate the _auto-config_ file on 'autoload_dump' Composer event.
	 *
	 * @param \Composer\Script\Event $event
	 */
	static public function on_autoload_dump(\Composer\Script\Event $event)
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

	/**
	 * Adds the "config" directories found in the app paths to `config-path`.
	 *
	 * @param array $autoconfig
	 */
	static public function filter_autoconfig(array &$autoconfig, $root)
	{
		$directories = \ICanBoogie\resolve_app_paths($root);

		foreach ($directories as $directory)
		{
			if (file_exists($directory . 'config'))
			{
				$autoconfig['config-path'][$directory . 'config'] = 20;
			}
		}
	}
}
