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

use Composer\Autoload\AutoloadGenerator;

/**
 * @codeCoverageIgnore
 */
final class FakeAutoloadGenerator extends AutoloadGenerator
{
	static public function sort_package_map(AutoloadGenerator $generator, array $packageMap): array
	{
		return $generator->sortPackageMap($packageMap);
	}
}
