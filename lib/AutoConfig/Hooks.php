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
	 * Alters the autoconfig according to the directories found in "<root>/protected".
	 *
	 * @param array $autoconfig
	 */
	static public function filter_autoconfig(array &$autoconfig)
	{
		$protected_root = $autoconfig['root'] . DIRECTORY_SEPARATOR . 'protected' . DIRECTORY_SEPARATOR;
		$tries = [ $protected_root . 'all' ];

		if (PHP_SAPI == 'cli')
		{
			$tries[] = $protected_root . 'cli';
		}
		else
		{
			$server_name = empty($_SERVER['SERVER_NAME']) ? null : $_SERVER['SERVER_NAME'];

			if (strpos($server_name, 'www.') === 0)
			{
				$server_name = substr($server_name, 4);
			}

			if ($server_name && file_exists($protected_root . $server_name))
			{
				$tries[] = $protected_root . $server_name;
			}
			else
			{
				$tries[] = $protected_root . 'default';
			}
		}

		foreach ($tries as $try)
		{
			if (!file_exists($try))
			{
				continue;
			}

			$root = $try . DIRECTORY_SEPARATOR;

			if (file_exists($root . 'config'))
			{
				$autoconfig['config-path'][$root . 'config'] = 20;
			}

			if (file_exists($root . 'locale'))
			{
				$autoconfig['locale-path'][] = $root . 'locale';
			}

			if (file_exists($root . 'modules'))
			{
				$autoconfig['module-path'][] = $root . 'modules';
			}
		}
	}
}
