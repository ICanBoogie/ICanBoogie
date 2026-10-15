<p><img height="120" src="https://raw.githubusercontent.com/ICanBoogie/app-hello/master/web/assets/icanboogie.svg" alt="ICanBoogie" /></p>

[![Release](https://img.shields.io/packagist/v/ICanBoogie/ICanBoogie.svg)](https://packagist.org/packages/icanboogie/icanboogie)
[![Packagist](https://img.shields.io/packagist/dt/icanboogie/icanboogie.svg)](https://packagist.org/packages/icanboogie/icanboogie)

__ICanBoogie__ is a high-performance micro-framework. It is written with speed, flexibility, and
lightness in mind. __ICanBoogie__ doesn't try to be an all-in-one do-it-all solution but provides
the essential features to quickly and easily build web applications. It is easily extensible, and a
variety of packages are available to complement its features with
[rendering](https://github.com/icanboogie/render), [views](https://github.com/icanboogie/view),
[routing](https://github.com/icanboogie/routing),
[operations](https://github.com/icanboogie/operation),
[internationalization](https://github.com/icanboogie/cldr),
[translation](https://github.com/icanboogie/i18n),
[ActiveRecord](https://github.com/icanboogie/activerecord),
[facets](https://github.com/icanboogie/facets), [mailer](https://github.com/icanboogie/mailer)…





### What does _micro_ mean?

_"Micro"_ means that the core features of ICanBoogie are kept to the essential; the core is simple
but greatly extensible. For instance, ICanBoogie won't force an ORM on you, although its
[ActiveRecord](https://github.com/ICanBoogie/ActiveRecord) implementation is pretty nice. In the
same fashion, its routing mechanisms are quite agnostic and let you use your very own
dispatcher if you want to.





### Configuration and conventions

ICanBoogie and its components are usually very configurable and come with sensible defaults and a
few conventions. Configurations are usually located in "config" folders, while locale messages are
usually located in "locale" folders. Components configure themselves thanks to ICanBoogie's
[Autoconfig](docs/autoconfig.md) feature, and often don't require more that a line in your
`composer.json` file.





### Acknowledgement

[MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org), and, of course,
[Baccara](http://www.youtube.com/watch?v=KGuFn0RPgaE).





## Documentation

The documentation for 7.x is in the [docs](docs/README.md) directory:

- [Getting started](docs/getting-started.md)
- [Application lifecycle](docs/lifecycle.md)
- [Autoconfig](docs/autoconfig.md)
- [Configuration](docs/configuration.md)
- [Services](docs/services.md)
- [Events](docs/events.md)
- [Console](docs/console.md)
- [Upgrading](docs/upgrading.md)





## Helpers

The following helper functions are defined:

- `app()`: Returns the `Application` instance, or throws `Application\InvalidState` if it has
not been instantiated yet.
- `boot()`: Instantiates an `Application` instance with the _autoconfig_ and boots it.





----------





## Installation

```shell
composer require icanboogie/icanboogie
```

The following packages are required, you might want to check them out:

- [icanboogie/common](https://github.com/ICanBoogie/Common)
- [icanboogie/inflector](https://github.com/ICanBoogie/Inflector)
- [icanboogie/datetime](https://github.com/ICanBoogie/DateTime)
- [icanboogie/prototype](https://github.com/ICanBoogie/Prototype)
- [icanboogie/event](https://github.com/ICanBoogie/Event)
- [icanboogie/http](https://github.com/ICanBoogie/HTTP)
- [icanboogie/routing](https://github.com/ICanBoogie/Routing)

The following packages can also be installed for additional features:

- [icanboogie/render][]: A rendering API.
- [icanboogie/view][]: Adds views to controllers.
- [icanboogie/activerecord](https://github.com/ICanBoogie/ActiveRecord): ActiveRecord Object-relational mapping.
- [icanboogie/cldr](https://github.com/ICanBoogie/CLDR): Provides internationalization for
your application.
- [icanboogie/i18n](https://github.com/ICanBoogie/I18n): Provides localization for your application
and additional internationalization helpers.
- [icanboogie/image](https://github.com/ICanBoogie/Image): Provides image resizing, filling,
and color resolving.
- [icanboogie/module][]: Provides framework extensibility using modules.

The following bindings are available to help in integrating components:

- [icanboogie/bind-activerecord][]
- [icanboogie/bind-cldr][]
- [icanboogie/bind-render][]
- [icanboogie/bind-view][]



## Continuous Integration

The project is continuously tested by [GitHub Actions](https://github.com/ICanBoogie/ICanBoogie/actions).

[![Tests](https://github.com/ICanBoogie/ICanBoogie/actions/workflows/test.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/ICanBoogie/actions/workflows/test.yml)
[![Static Analysis](https://github.com/ICanBoogie/ICanBoogie/actions/workflows/static-analysis.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/ICanBoogie/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/ICanBoogie/ICanBoogie/actions/workflows/code-style.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/ICanBoogie/actions/workflows/code-style.yml)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in
this project and its community, you are expected to uphold this code.



## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.



[icanboogie/accessor]:          https://github.com/ICanBoogie/Accessor
[icanboogie/bind-activerecord]: https://github.com/ICanBoogie/bind-activerecord
[icanboogie/bind-cldr]:         https://github.com/ICanBoogie/bind-cldr
[icanboogie/bind-render]:       https://github.com/ICanBoogie/bind-render
[icanboogie/bind-view]:         https://github.com/ICanBoogie/bind-view
[icanboogie/module]:            https://github.com/ICanBoogie/Module
[icanboogie/prototype]:         https://github.com/ICanBoogie/Prototype
[icanboogie/render]:            https://github.com/ICanBoogie/Render
[icanboogie/view]:              https://github.com/ICanBoogie/View
[Prototype package]:            https://github.com/ICanBoogie/Prototype
