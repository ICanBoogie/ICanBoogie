# Services

ICanBoogie uses [Symfony's DependencyInjection](https://symfony.com/doc/current/components/dependency_injection.html)
container, bound by `icanboogie/bind-symfony-dependency-injection`. The container is available as
`$app->container`.

## Obtaining services

```php
<?php

use ICanBoogie\HTTP\Responder;

$responder = $app->service_for_class(Responder::class);
```

`service_for_class()` fetches a service by its class name. When the service id differs from the
class, use `service_for_id($id, $class)`, which asserts the service is an instance of `$class`
(assertions need to be enabled for the check to run).

`Application` implements the `ICanBoogie\ServiceProvider` interface with these two methods, so you
can type-hint the interface where you only need services.

## Defining services

Services are declared in `services.yml` files in config directories (see
[Autoconfig](autoconfig.md)). Autowiring is usually enabled by default for the file:

```yaml
services:
  _defaults:
    autowire: true

  console.command.cache.clear:
    class: ICanBoogie\Console\ClearCacheCommand
    tags:
    - { name: console.command, command: 'cache:clear' }
```

Your application can add its own `services.yml` to its config directory; packages ship theirs in
theirs.

## Injecting the application

Services can type-hint `Application`, as the console commands do:

```php
<?php

use ICanBoogie\Application;

final class ClearCacheCommand extends Command
{
    public function __construct(
        private readonly Application $app
    ) {
        parent::__construct();
    }
}
```

## Prefer injection to the global

`app()` is a convenience for entry points and glue code. In your own classes, depend on the services
or configs you need and let the container wire them, so your code stays testable.
