<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Debug;

use ICanBoogie\Config\Builder;
use ICanBoogie\Debug;

use function in_array;

/**
 * @implements Builder<Config>
 */
final class ConfigBuilder implements Builder
{
    public static function get_fragment_filename(): string
    {
        return 'debug';
    }

    private string $mode = Debug::MODE_DEV;

    public function set_mode(string $mode): self
    {
        assert(in_array($mode, [ Debug::MODE_DEV, Debug::MODE_STAGE, Debug::MODE_PRODUCTION ]));

        $this->mode = $mode;

        return $this;
    }

    public function build(): Config
    {
        return new Config(
            mode: $this->mode
        );
    }
}
