# ICanBoogie

[![Release](https://img.shields.io/packagist/v/ICanBoogie/ICanBoogie.svg)](https://packagist.org/packages/icanboogie/icanboogie)
[![Build Status](https://img.shields.io/travis/ICanBoogie/ICanBoogie/master.svg)](http://travis-ci.org/ICanBoogie/ICanBoogie)
[![HHVM](https://img.shields.io/hhvm/icanboogie/icanboogie.svg)](http://hhvm.h4cc.de/package/icanboogie/icanboogie)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/ICanBoogie/master.svg)](https://scrutinizer-ci.com/g/ICanBoogie/ICanBoogie)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/ICanBoogie/master.svg)](https://coveralls.io/r/ICanBoogie/ICanBoogie)
[![Packagist](https://img.shields.io/packagist/dt/icanboogie/icanboogie.svg)](https://packagist.org/packages/icanboogie/icanboogie)

__ICanBoogie__ is a high-performance micro-framework for PHP 5.5+. It is written with speed,
flexibility and lightness in mind. ICanBoogie doesn't try to be an all-in-one do-it-all solution
but provides the essential features to quickly and easily build web applications. It is easily
extensible, and a variety of packages are available to complement its features with
rendering, views, routing, operations, internationalization, translation, ORM, facets, mailer…

Together with [Brickrouge](http://brickrouge.org) and [Patron](https://github.com/Icybee/Patron),
ICanBoogie is one of the components that make the CMS [Icybee](http://icybee.org). You might want
to check these projects too.





### What does _micro_ mean?

_micro_ means that the core features of ICanBoogie are kept to the essential, the core is simple
but greatly extensible. For instance, ICanBoogie won't force an ORM on you, although its
[ActiveRecord](https://github.com/ICanBoogie/ActiveRecord) implementation is pretty nice. In the
same fashion, its routing mechanisms are quite agnostic and let you use your very own
dispatcher if you want to.





### Configuration and conventions

ICanBoogie and its components are usually very configurable and come with sensible defaults and a
few conventions. Configurations are usually located in "config" folders, while locale messages are
usually located in "locale" folders. Components configure themselves thanks to ICanBoogie's
_Autoconfig_ feature, and won't require much of you other than a line in your
`composer.json` file.





### Acknowledgement

[MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org),
[Yii](http://www.yiiframework.com), and of course [Bacara](http://www.youtube.com/watch?v=KGuFn0RPgaE).





## Working with ICanBoogie

ICanBoogie tries to leverage the magic features of PHP as much as possible: getters/setters,
invokable objects, array access, stringifiable objects, closures… to create a coherent framework
which requires less typing and most of all less guessing. As a result, applications created with
ICanBoogie have readable concise code and fluid flow.





### Getters and setters

Magic properties are used in favor of getter and setter methods (e.g. `getXxx()` or `setXxx()`).
For instance,  [DateTime][] instances provide a `minute` magic property instead of `getMinute()`
and `setMinute()` methods:

```php
<?php

use ICanBoogie\DateTime;

$time = new DateTime('2013-05-17 12:30:45', 'utc');
echo $time;         // 2013-05-17T12:30:45Z
echo $time->minute; // 30

$time->minute += 120;
echo $time;         // 2013-05-17T14:30:45Z
```

The getter/setter feature provided by [icanboogie/accessor][], and extended by
[icanboogie/prototype][], allows you to create read-only or write-only properties, 
façades properties, fallbacks to generate default values. Getters are setters are also often
using to control types, lazy load resources, and inject dependencies.





### Dependency injection

[icanboogie/prototype][] allows methods to be defined at runtime, and since getters and setters
are methods as well, this feature in often used as a mean to inject dependencies. What's great
about it is that dependencies are only _injected_ when they are required, not when to instance
is created.

The following example demonstrates how a shared database connection is obtained through a `db`
property:

```php
<?php

use ICanBoogie\ActiveRecord\Connection;
use ICanBoogie\Prototype;
use ICanBoogie\PrototypeTrait;

/**
 * @property-read Connection $db
 */
class A
{
	use PrototypeTrait;
	
	public function truncate()
	{
		$this->db("TRUNCATE my_table");
	}
}

Prototype::from("A")['get_db'] = function(A $a) {

	static $db;
	
	return $db ?: $db = new Connection("sqlite::memory:");

};
```





### Objects as strings

If a string represents a serialized set of data ICanBoogie usually provides a class to make its
manipulation easy. Instances can be created from strings, and in turn they can be used as strings.
This applies to dates and times, time zones, time zone locations, HTTP headers, HTTP responses,
database queries, and many more.

```php
<?php

use ICanBoogie\DateTime;

$time = new DateTime('2013-05-17 12:30:45', 'Europe/Paris');

echo $time;                           // 2013-05-17T12:30:45+0200
echo $time->minute;                   // 30
echo $time->zone;                     // Europe/Paris
echo $time->zone->offset;             // 7200
echo $time->zone->location;           // FR,48.86667,2.3333348
echo $time->zone->location->latitude; // 48.86667

use ICanBoogie\HTTP\Headers;

$headers = new Headers;
$headers['Cache-Control'] = 'no-cache';
echo $headers['Cache-Control'];       // no-cache

$headers['Cache-Control']->cacheable = 'public';
$headers['Cache-Control']->no_transform = true;
$headers['Cache-Control']->must_revalidate = false;
$headers['Cache-Control']->max_age = 3600;
echo $headers['Cache-Control'];       // public, max-age=3600, no-transform

use ICanBoogie\HTTP\Response;

$response = new Response('ok', 200);
echo $response;                       // HTTP/1.0 200 OK\r\nDate: Fri, 17 May 2013 15:08:21 GMT\r\n\r\nok

echo $app->models['pages']->own->visible->filter_by_nid(12)->order('created_on DESC')->limit(5);
// SELECT * FROM `pages` `page` INNER JOIN `nodes` `node` USING(`nid`) WHERE (`constructor` = ?) AND (`is_online` = ?) AND (site_id = 0 OR site_id = ?) AND (language = "" OR language = ?) AND (`nid` = ?) ORDER BY created_on DESC LIMIT 5
```





### Invokable objects 

Objects performing a main action are simply invoked to perform that action. For instance, a
prepared database statement is invoked to perform a command:

```php
<?php

# DB statements

$statement = $app->models['nodes']->prepare('UPDATE {self} SET title = ? WHERE nid = ?');
$statement("Title 1", 1);
$statement("Title 2", 2);
$statement("Title 3", 3);
```

This applies to database connections, models, requests, responses, translators… and many more.

```php
<?php

$pages = $app->models['pages'];
$pages('SELECT * FROM {self_and_related} WHERE YEAR(created_on) = 2013')->all;

# HTTP

use ICanBoogie\HTTP\Request;

$request = Request::from($_SERVER);
$response = $request();
$response();

# I18n translator

use ICanBoogie\I18n\Locale;

$translator = Locale::from('fr')->translator;
echo $translator('I can Boogie'); // Je sais danser le Boogie
```





### Collections as arrays

Collections of objects always provide an array interface, whether they are records in the
database, database connections, models, modules, header fields…

```php
<?php

$app->models['nodes'][123];   // fetch record with key 123 in nodes
$app->modules['nodes'];       // obtain the Nodes module
$app->connections['primary']; // obtain the primary database connection

$request['param1'];            // fetch param of the request named `param1`, returns `null` if it doesn't exists

$response->headers['Cache-Control'] = 'no-cache';
$response->headers['Content-Type'] = 'text/html; charset=utf-8';
```




### Creating an instance from data

Most classes provide a `from()` static method that create instances from various data types.
This is especially true for sub-classes of the [Prototyped][] class, which can create instances
from arrays of properties. ActiveRecords are a perfect example of this feature:

```php
<?php

use Icybee\Modules\Nodes\Node;

$node = new Node;
$node->uid = 1;
$node->title = "Title";

#or

$node = Node::from([ 'uid' => 1, 'title' => "Title" ]);
```

Some classes don't even provide a public constructor and rely solely on the `from()` method. For
instance, [Request][] instances can only by created using the `from()` method:

```php
<?php

use ICanBoogie\HTTP\Request;

# Creating the initial request from the $_SERVER array

$initial_request = Request::from($_SERVER);

# Creating a local XHR post request with some parameters

$custom_request = Request::from([

	'path' => '/path/to/controller',
	'is_post' => true,
	'is_local' => true,
	'is_xhr' => true,

	'request_params' => [

		'param1' => 'value1',
		'param2' => 'value2'

	]

]);
```





## The life and death of your application

Thanks to its Autoconfig system and a few conventions, running your application only requires
three lines:

```php
<?php

require 'vendor/autoload.php';

$app = ICanBoogie\boot();
$app();
```

1\. The first line is pretty common for applications using [Composer][], it creates and runs
its autoloader.

2\. On the second line the [Core][] instance is created with the _autoconfig_, its `boot()`
method is invoked, and the `ICanBoogie\Core::boot` event is fired. At this point ICanBoogie and
low-level components are configured and booted. Your application is ready to process requests.

3\. On the third line the application is run, which implies the following:

3.1\. The HTTP response code is set to 500, so that if a fatal error occurs the error message
won't be sent with the HTTP code 200 (Ok).

3.2\. The initial request is obtained and the `ICanBoogie\Core::run` event is fired with it.

3.3\. The request is executed to obtain a response.

3.4\. The response is executed to respond to the request. It should set the HTTP code to the
appropriate value.

3.5\. The `ICanBoogie\Core::terminate` event is fired at which point the application should be
terminated.





## Multi-site support

ICanBoogie has built-in multi-site support and can be configured for different domains or environments. Even
if you are dealing with only one domain, this feature can be used to provide different
configuration for the "dev", "stage", and "production" versions of a same application.

The intended location for your custom application code is in a separate "protected" directory, but
another directory can be defined with the `app-root` _autoconfig_ directive. The directory is
relative to the `root` directive.

ICanBoogie searches for "config" directories to add to the _autoconfig_. Packages may alter the _autoconfig_ as well. For instance, [icanboogie/module][] searches for "modules" directories.





### Instance name

The instance name of the application is used to resolve the application paths. It is usually defined by the `ICANBOOGIE_INSTANCE` environment variable.

If the variable is not defined, the instance name defaults as follows:

- The application runs from the CLI, "cli" is used as instance name.
- `$_SERVER['SERVER_NAME']` is defined, it is used as instance name.





### Resolving applications paths

Consider an application root directory with the following directories:

```
all
cli
default
dev
icanboogie.org
org
```

The directory "all" contains resources that are common to all instances. It is always added if present. The directory "default" is only added if there no directory matches the instance name.

To resolve the matching directory, the instance name is first broken into parts and the most
specific ones are removed until a corresponding directory is found. For instance, given the
server name `www.icanboogie.dev`, the following directories are tried:
`www.icanboogie.dev`, `icanboogie.dev`, and finally `dev`.

If the instance name cannot be resolved into a directory, "default" is used instead.





## Autoconfig

The _Autoconfig_ feature automatically generates a configuration file from the available low-level
components participating in the _Autoconfig_ process. Currently, it is used to define
configuration constructors, paths to component configurations, paths to locale message catalogs,
and paths to modules.





### Participating in the _autoconfig_ process

To participate in the _autoconfig_ process, packages need to define their _autoconfig_ fragment
in the `extra/icanboogie` section of their "composer.json" file. The file must match the
[composer-schema.json](lib/Autoconfig/composer-schema.json) schema. The following example
demonstrates how an application can specify the path to its configuration and locale messages.

```json
{
	"extra": {
		"icanboogie": {
			"config-path": "path/to/config",
			"locale-path": "path/to/locale"
		}
	}
}
```

**Note:** Packages can also define their _autoconfig_ fragment in a stand-alone "icanboogie.json"
file, beside their "composer.json" file, but using the "composer.json" file is recommended. The
file must match the [icanboogie-schema.json](lib/Autoconfig/icanboogie-schema.json) schema.





### Generating the _autoconfig_ file

The _autoconfig_ file is generated after the autoloader is dumped, during the
[`post-autoload-dump`](https://getcomposer.org/doc/articles/scripts.md) event emitted by [Composer][].
Thus, in order for the _autoconfig_ feature to work, a script for the event
is required in the _root_ package of the application:

```json
{
	"scripts": {
		"post-autoload-dump": "ICanBoogie\\Autoconfig\\Hooks::on_autoload_dump"
	}
}
```





### Obtaining the _autoconfig_

The _autoconfig_ is obtained using the `ICanBoogie\get_autoconfig()` function, and can be
used as is to instantiate the [Core][] instance. The function also updates the `app-root` and
`app-paths` values with the resolved application root and and resolved application paths
respectively.

```php
<?php

namespace ICanBoogie;

$app = new Core(get_autoconfig());
```

Additionally, the `ICanBoogie\AUTOCONFIG_PATHNAME` constant defines the absolute pathname to the
_autoconfig_ file.

**Note:** A fatal error is triggered if the _autoconfig_ file does not exists, which might
happen if the user forgot to add the `post-autoload-dump` hook in its "composer.json" file.





### Altering the _autoconfig_ at runtime

Filters defined with the `autoconfig-filters` key are invoked to alter the _autoconfig_ before
the `get_autoconfig()` function returns it. For instance, ICanBoogie uses this feature to add
"config" directories found in the application paths (using the multi-site support).

```json
{
	"extra": {
		"icanboogie": {
			"autoconfig-filters": [ "ICanBoogie\\Autoconfig\\Hooks::filter_autoconfig" ]
		}
	}
}
```





## Configuring the _core_

The [Core][] instance is configured with _core_ configuration fragments. The fragment used by your
application is usually located in the `/protected/all/config/core.php` file.

The following example demonstrates how to enable configs caching and how to specify the name
of the session and its scope.

```php
<?php

// protected/all/config/core.php

return [

	'cache configs' => true,

	'session' => [

			'name' => "ICanBoogie",
			'domain' => ".example.org"

		]
	]
];
```

Check ICanBoogie's "config/core.php" for a list of the available options and their default values.





### Specify a storage engine for synthesized configurations

A storage engine for synthesized configurations may be specified with the
`storage_for_configs` option. You may specify a class name or a callable that would return a
[Storage][] instance.

The default implementation returns a [FileStorage][] instance, or if APC is available a
[StorageCollection][] made of an [APCStorage][] instance and a [FileStorage][] instance.





### Specify a storage engine for variables

A storage engine for variables may be specified with the
`storage_for_variables` option. You may specify a class name or a callable that would return a
[Storage][] instance.

The default implementation returns a [FileStorage][] instance, or if APC is available a
[StorageCollection][] made of an [APCStorage][] instance and a [FileStorage][] instance.





## Events





### The application has booted

The `ICanBoogie\Core::boot` event of class [BootEvent][] is fired once the application has booted.
Third parties may use this event to alter the configuration or the components before the
application is ran.





### The application is running

The `ICanBoogie\Core::run` event of class [RunEvent][] is fired when the application is running.
Third parties may use this event to alter various states of the application, starting with the
initial request.





### The application is terminated

The `ICanBoogie\Core::terminate` event of class [TerminateEvent][] is fired after the response to
the initial request was sent and the application is about to be terminated. Third parties may
use this event to cleanup loose ends.





## Prototype methods

### `ICanBoogie\Prototyped::get_app`

The `app` magic property of [Prototyped][] instances returns the instance of the
application. The property is read-only and is only available after the [Core][] instance
has been created.

The [PrototypedBindings][] trait may be used to type hint instances.

```php
<?php

namespace ICanBoogie;

use ICanBoogie\Binding\PrototypedBindings;

/* @var $o Prototyped|PrototypedBindings */

$o = new Prototyped;
$o->app;
// throw ICanBoogie\PropertyNotDefined;

$app = boot();
$app === $o->app;
// true
```





## Helpers

The following helper functions are defined:

- `app()`: Returns the [Core][] instance, or throws [CoreNotInstantiated][] if it hasn't been instantiated yet.
- `boot()`: Instantiates a [Core][] instance with the _autoconfig_ and boots it.
- `log()`: Logs a debug message.
- `log_success()`: Logs a success message.
- `log_error()`: Logs an error message.
- `log_info()`: Logs an info message.
- `log_time()`: Logs a debug message associated with a timing information.





----------





## Requirements

The minimum requirement is PHP 5.5.





## Installation

The recommended way to install this package is through [Composer](http://getcomposer.org/):

```
$ composer require icanboogie/icanboogie
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
- [icanboogie/operation](https://github.com/ICanBoogie/Operation): Operation oriented controllers API.

The following bindings are available to help in integrating components:

- [icanboogie/bind-activerecord][]
- [icanboogie/bind-cldr][]
- [icanboogie/bind-render][]
- [icanboogie/bind-view][]





### Cloning the repository

The package is [available on GitHub](https://github.com/ICanBoogie/ICanBoogie), its repository can be
cloned with the following command line:

	$ git clone https://github.com/ICanBoogie/ICanBoogie.git





## Documentation

The documentation for the package and its dependencies can be generated with the `make doc`
command. The documentation is generated in the `docs` directory using [ApiGen](http://apigen.org/).
The package directory can later by cleaned with the `make clean` command.

The documentation for the complete framework is also available online: <http://icanboogie.org/docs/>





## Testing

The test suite is ran with the `make test` command. [Composer](http://getcomposer.org/) is
automatically installed as well as all dependencies required to run the suite. You can later
clean the directory with the `make clean` command.

The package is continuously tested by [Travis CI](http://about.travis-ci.org/).

[![Build Status](https://img.shields.io/travis/ICanBoogie/ICanBoogie/master.svg)](http://travis-ci.org/ICanBoogie/ICanBoogie)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/ICanBoogie/master.svg)](https://coveralls.io/r/ICanBoogie/ICanBoogie)





## License

**ICanBoogie** is licensed under the New BSD License - See the [LICENSE](LICENSE) file for details.





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

[Composer]: http://getcomposer.org/

[DateTime]:            http://api.icanboogie.org/datetime/1.1/class-ICanBoogie.DateTime.html
[TimeZone]:            http://api.icanboogie.org/datetime/1.1/class-ICanBoogie.TimeZone.html
[Request]:             http://api.icanboogie.org/http/2.5/class-ICanBoogie.HTTP.Request.html
[BootEvent]:           http://api.icanboogie.org/icanboogie/2.4/class-ICanBoogie.Core.BootEvent.html
[Core]:                http://api.icanboogie.org/icanboogie/2.4/class-ICanBoogie.Core.html
[CoreNotInstantiated]: http://api.icanboogie.org/icanboogie/2.4/class-ICanBoogie.CoreNotInstantiated.html
[PrototypedBindings]:  http://api.icanboogie.org/icanboogie/2.4/class-ICanBoogie.Binding.PrototypedBindings.html
[RunEvent]:            http://api.icanboogie.org/icanboogie/2.4/class-ICanBoogie.Core.RunEvent.html
[TerminateEvent]:      http://api.icanboogie.org/icanboogie/2.4/class-ICanBoogie.Core.TerminateEvent.html
[Prototyped]:          http://api.icanboogie.org/prototype/2.3/class-ICanBoogie.Prototyped.html
[APCStorage]:          http://api.icanboogie.org/storage/1.2/class-ICanBoogie.Storage.APCStorage.html
[FileStorage]:         http://api.icanboogie.org/storage/1.2/class-ICanBoogie.Storage.FileStorage.html
[Storage]:             http://api.icanboogie.org/storage/1.2/class-ICanBoogie.Storage.Storage.html
[StorageCollection]:   http://api.icanboogie.org/storage/1.2/class-ICanBoogie.Storage.StorageCollection.html
