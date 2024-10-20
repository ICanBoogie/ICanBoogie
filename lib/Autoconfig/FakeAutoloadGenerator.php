<?php

namespace ICanBoogie\Autoconfig;

use Composer\Autoload\AutoloadGenerator;
use Composer\Package\PackageInterface;

/**
 * @codeCoverageIgnore
 */
final class FakeAutoloadGenerator extends AutoloadGenerator
{
    /**
     * @param array<int, array{0: PackageInterface, 1: string|null}> $packageMap
     *
     * @return array<int, array{0: PackageInterface, 1: string|null}>
     */
    public static function sort_package_map(AutoloadGenerator $generator, array $packageMap): array
    {
        return $generator->sortPackageMap($packageMap);
    }
}
