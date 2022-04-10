<p><img height="120" src="https://cdn.rawgit.com/ICanBoogie/app-hello/master/web/assets/icanboogie.svg" alt="ICanBoogie" /></p>

[![Release](https://img.shields.io/packagist/v/ICanBoogie/ICanBoogie.svg)](https://packagist.org/packages/icanboogie/icanboogie)
[![Build Status](https://img.shields.io/github/workflow/status/ICanBoogie/ICanBoogie/test)](https://github.com/ICanBoogie/ICanBoogie/actions?query=workflow%3Atest)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/ICanBoogie/master.svg)](https://scrutinizer-ci.com/g/ICanBoogie/ICanBoogie)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/ICanBoogie/master.svg)](https://coveralls.io/r/ICanBoogie/ICanBoogie)
[![Packagist](https://img.shields.io/packagist/dt/icanboogie/icanboogie.svg)](https://packagist.org/packages/icanboogie/icanboogie)

**ICanBoogie** is a high-performance micro-framework. It is written with speed, flexibility and
lightness in mind. **ICanBoogie** doesn't try to be an all-in-one do-it-all solution but provides the
essential features to quickly and easily build web applications. It is easily extensible, and a
variety of packages are available to complement its features with [rendering](https://github.com/icanboogie/render), [views](https://github.com/icanboogie/view), [routing](https://github.com/icanboogie/routing),
[operations](https://github.com/icanboogie/operation), [internationalization](https://github.com/icanboogie/cldr), [translation](https://github.com/icanboogie/i18n), [ActiveRecord](https://github.com/icanboogie/activerecord), [facets](https://github.com/icanboogie/facets), [mailer](https://github.com/icanboogie/mailer)…

Together with [Brickrouge](http://brickrouge.org) and [Patron](https://github.com/Icybee/Patron),
**ICanBoogie** is one of the components that make the CMS [Icybee](http://icybee.org). You might want
to check these projects too.





### What does _micro_ mean?

_"Micro"_ means that the core features of ICanBoogie are kept to the essential, the core is simple
but greatly extensible. For instance, ICanBoogie won't force an ORM on you, although its
[ActiveRecord](https://github.com/ICanBoogie/ActiveRecord) implementation is pretty nice. In the
same fashion, its routing mechanisms are quite agnostic and let you use your very own
dispatcher if you want to.





### Configuration and conventions

ICanBoogie and its components are usually very configurable and come with sensible defaults and a
few conventions. Configurations are usually located in "config" folders, while locale messages are
usually located in "locale" folders. Components configure themselves thanks to ICanBoogie's
[Autoconfig][] feature, and won't require much of you other than a line in your
`composer.json` file.





### Acknowledgement

[MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org),
[Yii](http://www.yiiframework.com), and of course [Bacara](http://www.youtube.com/watch?v=KGuFn0RPgaE).





## Summary

- [Working with ICanBoogie](https://icanboogie.org/docs/4.0/icanboogie#working-with-icanboogie)
    - [Getters and setters](https://icanboogie.org/docs/4.0/icanboogie#getters-and-setters)
    - [Dependency injection, inversion of control](https://icanboogie.org/docs/4.0/icanboogie#dependency-injection-inversion-of-control)
    - [Objects as strings](https://icanboogie.org/docs/4.0/icanboogie#objects-as-strings)
    - [Invokable objects](https://icanboogie.org/docs/4.0/icanboogie#invokable-objects)
    - [Collections as arrays](https://icanboogie.org/docs/4.0/icanboogie#collections-as-arrays)
    - [Creating an instance from data](https://icanboogie.org/docs/4.0/icanboogie#creating-an-instance-from-data)
- [The life and death of your application](https://icanboogie.org/docs/4.0/life-and-death)
- [Multi-site support](https://icanboogie.org/docs/4.0/multi-site)
    - [Instance name](https://icanboogie.org/docs/4.0/multi-site#instance-name)
    - [Resolving applications paths](https://icanboogie.org/docs/4.0/multi-site#resolving-applications-paths)
- [Autoconfig](https://icanboogie.org/docs/4.0/autoconfig)
    - [Participating in the _autoconfig_ process](https://icanboogie.org/docs/4.0/autoconfig#participating-in-the-autoconfig-process)
    - [Generating the _autoconfig_ file](https://icanboogie.org/docs/4.0/autoconfig#generating-the-autoconfig-file)
    - [Obtaining the _autoconfig_](https://icanboogie.org/docs/4.0/autoconfig#obtaining-the-autoconfig)
    - [Altering the _autoconfig_ at runtime](https://icanboogie.org/docs/4.0/autoconfig#altering-the-autoconfig-at-runtime)
- [Configuring the application](https://icanboogie.org/docs/4.0/configuration)
- [Events](https://icanboogie.org/docs/4.0/life-and-death#events)
- [Bindings](https://icanboogie.org/docs/4.0/bindings)
    - [Prototyped bindings](https://icanboogie.org/docs/4.0/bindings#prototyped-bindings)





## Routes

The package provides a controller for the `/api/ping` route, which may be used to renew a session,
if one existed in the first place. When the `timer` query parameter is present, the controller
gives timing information as well.

```php
<?php

use ICanBoogie\HTTP\Request;

$request = Request::from('/api/ping?timer');

echo $request()->body;
// pong, in 4.875 ms (ready in 3.172 ms)
```





## Helpers

The following helper functions are defined:

- `app()`: Returns the [Application][] instance, or throws [ApplicationNotInstantiated][] if it has
not been instantiated yet.
- `boot()`: Instantiates a [Application][] instance with the _autoconfig_ and boots it.
- `log()`: Logs a debug message.
- `log_success()`: Logs a success message.
- `log_error()`: Logs an error message.
- `log_info()`: Logs an info message.
- `log_time()`: Logs a debug message associated with a timing information.





----------





## Installation

```bash
composer require icanboogie/icanboogie
```

Don't forget to modify the _script_ section of your "composer.json" file if you want to benefit
from the _autoconfig_ feature:

```json
{
    "scripts": {
        "post-autoload-dump": "ICanBoogie\\Autoconfig\\Hooks::on_autoload_dump"
    }
}
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
- [icanboogie/operation][]: Operation oriented controllers API.

The following bindings are available to help in integrating components:

- [icanboogie/bind-activerecord][]
- [icanboogie/bind-cldr][]
- [icanboogie/bind-render][]
- [icanboogie/bind-view][]





## Documentation

The documentation for the package and its dependencies can be generated with the `make doc`
command. The documentation is generated in the `docs` directory using [ApiGen](http://apigen.org/).
The package directory can later by cleaned with the `make clean` command.

The documentation for the complete framework is also available online: <https://icanboogie.org/docs/>





## Testing

Run `make test-container` to create and log into the test container, then run `make test` to run the
test suite. Alternatively, run `make test-coverage` to run the test suite with test coverage. Open
`build/coverage/index.html` to see the breakdown of the code coverage.





## License

**ICanBoogie** is released under the [New BSD License](LICENSE).





[icanboogie/accessor]:          https://github.com/ICanBoogie/Accessor
[icanboogie/bind-activerecord]: https://github.com/ICanBoogie/bind-activerecord
[icanboogie/bind-cldr]:         https://github.com/ICanBoogie/bind-cldr
[icanboogie/bind-render]:       https://github.com/ICanBoogie/bind-render
[icanboogie/bind-view]:         https://github.com/ICanBoogie/bind-view
[icanboogie/module]:            https://github.com/ICanBoogie/Module
[icanboogie/operation]:         https://github.com/ICanBoogie/Operation
[icanboogie/prototype]:         https://github.com/ICanBoogie/Prototype
[icanboogie/render]:            https://github.com/ICanBoogie/Render
[icanboogie/view]:              https://github.com/ICanBoogie/View
[Prototype package]:            https://github.com/ICanBoogie/Prototype

[ApplicationNotInstantiated]:   https://icanboogie.org/api/icanboogie/4.0/class-ICanBoogie.ApplicationNotInstantiated.html

[Application]:                  https://icanboogie.org/docs/4.0/the-application-class
[Autoconfig]:                   https://icanboogie.org/docs/4.0/autoconfig
[Composer]:                     http://getcomposer.org/
