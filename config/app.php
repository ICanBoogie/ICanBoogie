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

return fn(AppConfigBuilder $config) => $config
    ->set_storage_for_config([ Hooks::class, 'create_storage_for_configs' ])
    ->set_storage_for_vars([ Hooks::class, 'create_storage_for_vars' ])
    ->set_session([
        SessionOptions::OPTION_NAME => 'ICanBoogie'
    ]);
