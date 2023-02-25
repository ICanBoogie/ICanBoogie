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
use Throwable;

use function is_string;
use function realpath;

/**
 * @codeCoverageIgnore
 */
final class Hooks
{
    /**
     * Generate the _autoconfig_ file on 'autoload_dump' Composer event.
     *
     * @throws Throwable
     */
    public static function on_autoload_dump(Event $event): void
    {
        $composer = $event->getComposer();
        $package = $composer->getPackage();
        $generator = $composer->getAutoloadGenerator();
        $packages = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $packageMap = $generator->buildPackageMap($composer->getInstallationManager(), $package, $packages);
        $sorted = FakeAutoloadGenerator::sort_package_map($generator, $packageMap);

        $vendor_dir = $composer->getConfig()->get('vendor-dir');
        assert(is_string($vendor_dir));
        $destination = realpath($vendor_dir) . "/icanboogie/autoconfig.php";
        $config = new AutoconfigGenerator($sorted, $destination);
        $config();
    }
}
