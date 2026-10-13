# Configuration

ICanBoogie configures itself and its components with **config fragments** and **config builders**.

## Fragments

A fragment is a PHP file, named after a builder, located in a config directory registered through
[Autoconfig](autoconfig.md). It returns a callable that receives the builder:

```php
<?php

// config/app.php

namespace ICanBoogie;

return fn(AppConfigBuilder $config) => $config
    ->enable_config_caching()
    ->set_var('/path/to/var');
```

To build a configuration, the provider creates the builder, then requires the fragment of every
config path that has one, ordered by weight (framework, then modules, then the application), calling
each callable with the same builder. A later fragment overrides what an earlier one set. Finally
`build()` returns the immutable config object.

If a fragment throws, a `RuntimeException` naming the offending file is raised.

## Obtaining a configuration

```php
<?php

use ICanBoogie\AppConfig;

$app = \ICanBoogie\app();

$config = $app->config_for_class(AppConfig::class);
// or, for AppConfig only:
$config = $app->config;
```

A config class with no registered builder throws `NoBuilderDefined`.

## Defining your own configuration

1. Write an immutable config class.
2. Write a builder implementing `ICanBoogie\Config\Builder`, whose `get_fragment_filename()`
   returns the fragment name and `build()` returns the config.
3. Register the pair in your `composer.json`:

   ```json
   {
     "extra": {
       "icanboogie": {
         "config-path": "config/",
         "config-constructor": {
           "Acme\\MyConfig": "Acme\\MyConfigBuilder"
         }
       }
     }
   }
   ```

4. Run `composer dump-autoload` (or install/update) to regenerate the autoconfig.

## `AppConfig`

`AppConfig` is built by `AppConfigBuilder` from the `app.php` fragments.

| Builder method                                           | Description                                                 |
|----------------------------------------------------------|-------------------------------------------------------------|
| `enable_config_caching()` / `disable_config_caching()`   | Whether built configurations are cached. Default: disabled. |
| `enable_catalog_caching()` / `disable_catalog_caching()` | Whether message catalogs are cached. Default: disabled.     |
| `set_storage_for_config(callable)`                       | Factory `fn(Application): Storage` for the config cache.    |
| `set_storage_for_vars(callable)`                         | Factory `fn(Application): Storage` for `$app->vars`.        |
| `set_error_handler(callable)`                            | Installed with `set_error_handler()` on instantiation.      |
| `set_exception_handler(callable)`                        | Installed with `set_exception_handler()` on instantiation.  |
| `set_var(string)`                                        | The `var` directory. Default: `var/`.                       |
| `set_var_cache(string)`                                  | Default: `{var}/cache/`.                                    |
| `set_var_cache_configs(string)`                          | Default: `{var_cache}/configs/`.                            |
| `set_var_files(string)`                                  | Default: `{var}/files/`.                                    |
| `set_var_tmp(string)`                                    | Default: `{var}/tmp/`.                                      |
| `set_var_lib(string)`                                    | Default: `{var}/lib/`.                                      |

Directory values always end with a directory separator. Only `var` is the base for the defaults; the
other directories needn't be subfolders of it.

The framework's own `config/app.php` sets the storage factories to
`Hooks::create_storage_for_configs` and `Hooks::create_storage_for_vars`. Both return a
`FileStorage`, fronted by an `APCStorage` when APCu is available.

## Config caching

With `enable_config_caching()`, the application wraps its provider in a `CachedConfigProvider`,
which stores built configurations in `$app->storage_for_configs`. Cache keys are derived from the
config paths and the config class; changes to fragment files are **not** detected, so clear the
cache after changing configuration, using `cache:clear` (see [Console](console.md)).

## Non-volatile variables

`$app->vars` is a `Storage` backed by the factory given to `set_storage_for_vars()`, by default
files under `var_lib`:

```php
<?php

$app->vars['last_import'] = time();
```
