# ICanBoogie 7.x Documentation

__ICanBoogie__ is a high-performance micro-framework for PHP 8.4+. The core is kept to the
essentials: an `Application` that boots, runs, and terminates, a configuration system, a service
container, and events. Everything else (routing, HTTP, ActiveRecord, rendering…) comes from separate
packages.

## Contents

1. [Getting started](getting-started.md): installation, booting, running, and testing an application.
2. [Application lifecycle](lifecycle.md): states, `boot()`, `run()`, termination, and the events emitted.
3. [Autoconfig](autoconfig.md): how `composer.json` is turned into the application's low-level configuration.
4. [Configuration](configuration.md): config fragments, builders, and `AppConfig`.
5. [Services](services.md): the dependency injection container and `services.yml`.
6. [Events](events.md): attaching hooks, and the events emitted by the application.
7. [Console](console.md): the `cache:clear` and `configs:list` commands.
8. [Upgrading from 6.x](upgrading.md)

## Related packages

The package requires the following packages, which have their own documentation:

- [icanboogie/autoconfig](https://github.com/ICanBoogie/Autoconfig)
- [icanboogie/config](https://github.com/ICanBoogie/Config)
- [icanboogie/bind-event](https://github.com/ICanBoogie/bind-event) ([icanboogie/event](https://github.com/ICanBoogie/Event))
- [icanboogie/bind-http](https://github.com/ICanBoogie/bind-http) ([icanboogie/http](https://github.com/ICanBoogie/HTTP))
- [icanboogie/bind-routing](https://github.com/ICanBoogie/bind-routing) ([icanboogie/routing](https://github.com/ICanBoogie/Routing))
- [icanboogie/bind-prototype](https://github.com/ICanBoogie/bind-prototype) ([icanboogie/prototype](https://github.com/ICanBoogie/Prototype))
- [icanboogie/bind-symfony-dependency-injection](https://github.com/ICanBoogie/bind-symfony-dependency-injection)
- [icanboogie/console](https://github.com/ICanBoogie/Console)
- [icanboogie/service](https://github.com/ICanBoogie/Service)
