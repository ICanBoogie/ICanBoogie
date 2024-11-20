<?php

namespace ICanBoogie;

return fn(AppConfigBuilder $config) => $config
    ->set_storage_for_config([ Hooks::class, 'create_storage_for_configs' ])
    ->set_storage_for_vars([ Hooks::class, 'create_storage_for_vars' ]);
