# ICanBoogie [![Build Status](https://travis-ci.org/ICanBoogie/ICanBoogie.svg?branch=2.0)](https://travis-ci.org/ICanBoogie/ICanBoogie)

__ICanBoogie__ is a high-performance micro framework for PHP 5.4+. It is written with speed,
flexibility and lightness in mind. ICanBoogie doesn't try to be an all-in-one do-it-all solution
but provides the essential features to quickly and easily build web applications.

ICanBoogie packages offers the following features: Prototypes, ActiveRecords, Internationalization,
Modules, a RESTful API, Request/Dispatch/Rescue/Response, Operations, Events, Sessions, Routing,
Caching and more.

Together with [Brickrouge](http://brickrouge.org) and [Patron](https://github.com/Icybee/Patron),
ICanBoogie is one of the components that make the CMS [Icybee](http://icybee.org). You might want
to check these projects too.





### Acknowledgement

[MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org),
[Yii](http://www.yiiframework.com), and of course [Bacara](http://www.youtube.com/watch?v=KGuFn0RPgaE).





## Working with ICanBoogie

ICanBoogie tries to leverage the magic features of PHP as much as possible: getters/setters,
invokable objects, array access, stringifiable objects, closures… with the goal of creating a
coherent framework which requires less typing and most of all less guessing.

Applications created with ICanBoogie often have a very concise code, and a fluid flow.





### Getters and setters

Magic properties are used in favour of getter and setter methods (e.g. `getXxx()` or `setXxx()`).
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

The getter/setter feature provided by the [Prototype package][] allows you to create read-only or
write-only properties, type control, properties façades, fallbacks to generate default values,
forwarding properties, lazy loading…





#### Type control

Getters and setters are often used to control a property type. For instance, the `timezone`
property of the [Core][] instance always returns a [TimeZone][] instance no matter what it is
set to:

```php
<?php

$core->timezone = 3600;
echo get_class($core->timezone); // ICanBoogie\TimeZone
echo $core->timezone;            // Europe/Paris

$core->timezone = 'Europe/Madrid';
echo get_class($core->timezone); // ICanBoogie\TimeZone
echo $core->timezone;            // Europe/Madrid
```





#### Using getters to fallback to default values

Because getters are invoked when their corresponding property is innacessible, and because
an unset property is innacessible, it is possible to define getters that return default values.
The following example demonstrates how a `slug` getter can be defined to generate a default
slug from the `title` property when the `slug` property is inaccessible:

```php
<?php

class Node
{
	public $title;
	public $slug;

	public function __construct($title, $slug=null)
	{
		$this->title = $title;

		if ($slug)
		{
			$this->slug = $slug;
		}
		else
		{
			unset($this->slug);
		}
	}

	public function get_slug()
	{
		return \ICanBoogie\normalize($this->title);
	}
}

$node = new Node('A nice title');
echo $node->slug;           // a-nice-title

$node->slug = "nice-title"
echo $node->slug;           // nice-title

unset($node->slug);
echo $node->slug;           // a-nice-title
```






### Invokable objects

Objects performing a main action are simply invoked to perform that action. For instance, a
prepared database statement whose main purpose is to query the database doesn't have an
`execute()` method, it is invoked to perform its purpose:

```php
<?php

# DB statements

$statement = $core->models['nodes']->prepare('UPDATE {self} SET title = ? WHERE nid = ?');
$statement("Title 1", 1);
$statement("Title 2", 2);
$statement("Title 3", 3);
```

This applies to database connections, models, requests, responses, translators… and many more.

```php
<?php

$pages = $core->models['pages'];
$pages('SELECT * FROM {self_and_related} WHERE YEAR(created_on) = 2013')->all;

# HTTP

use ICanBoogie\HTTP\Request;

$request = Request::from($_SERVER);
$response = $request();
$response();

# I18n translator

use ICanBoogie\I18n\Locale;

$translator = Locale::get('fr')->translator;
echo $translator('I can Boogie'); // Je sais danser le Boogie
```





### Collections as arrays

Collections of objects are always managed as arrays, whether they are records in the database,
models, modules, database connections…

```php
<?php

$core->models['nodes'][123];   // fetch record with key 123 in nodes
$core->modules['nodes'];       // obtain the Nodes module
$core->connections['primary']; // obtain the primary database connection

$request['param1'];            // fetch param of the request named `param1`, returns `null` if it doesn't exists

$response->headers['Cache-Control'] = 'no-cache';
$response->headers['Content-Type'] = 'text/html; charset=utf-8';
```




### Objects as strings

A lot of objects in ICanBoogie can be used as strings:

```php
<?php

use ICanBoogie\DateTime;

$time = new DateTime('2013-05-17 12:30:45', 'Europe/Paris');

echo $time;                           // 2013-05-17T12:30:45+0200
echo $time->minute;                   // 30
echo $time->zone;                     // Europe/Paris
echo $time->zone->offset;             // 7200
echo $time->zone->location;           // FR,48.86667,2.3333348.86667
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

echo $core->models['pages']->own->visible->filter_by_nid(12)->order('created_on DESC')->limit(5);
// SELECT * FROM `pages` `page` INNER JOIN `nodes` `node` USING(`nid`) WHERE (`constructor` = ?) AND (`is_online` = ?) AND (siteid = 0 OR siteid = ?) AND (language = "" OR language = ?) AND (`nid` = ?) ORDER BY created_on DESC LIMIT 5
```





### Creating an instance from data

Most classes provide a `from()` static method that can be used to create an instance of that class
from various data type. This is especially true for sub-classes of the [Object][] class, which
can create instances from arrays of properties. ActiveRecords are a perfect example of this
feature:

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





## Auto-config

_Auto-config_ is a feature of ICanBoogie that automatically generate a configuration file from
the low-level components available. Currently, it defines configuration constructors; paths to
component configurations; paths to locale message catalogs; and paths to modules.

To participate in the _auto-config_ process, packages define a "icanboogie.json" file matching
the [icanboogie-schema.json](auto-config/icanboogie-schema.json) schema. This file
can also be defined at the root of the application, beside the "composer.json" file, if the
application provides its own configuration, locale messages or modules.

The _auto-config_ file is generated after the autoloader is dumped, during the
[`post-autoload-dump`](https://getcomposer.org/doc/articles/scripts.md) emitted by [Composer][].
Thus, in order for the _auto-config_ feature to work, a script for the event
is required in the _root_ package of the application:

```json
{
	"scripts": {
		"post-autoload-dump": "ICanBoogie\\AutoConfig\\Generator::on_autoload_dump"
	}
}
```





### Using the _auto-config_ to instantiate the Core

Using the _auto-config_ feature, the [Core][] instance can be created very easily:

```php
<?php

$core = new ICanBoogie\Core(require ICanBoogie\AUTOCONFIG_PATHNAME);
# or
$core = new ICanBoogie\Core( ICanBoogie\get_autoconfig() );
```





## Events





### The core is running

The `ICanBoogie\Core::run` event of class [ICanBoogie\Core\RunEvent](http://icanboogie.org/docs/class-ICanBoogie.Core.RunEvent.html)
is fired when the core is running.

Third parties may use this event to alter various states of the application, starting with the
initial request.

The following code illustrate how the event can be used to retrieve the website corresponding to
the request and select the locale and time zone that should be used by the framework. Also, the
code patches the `contextualize()` and `decontextualize()` routing helpers to alter the paths
according to the website's path.

```php
<?php

namespace Icybee\Modules\Sites;

use ICanBoogie\Routing;

$core->events->attach(function(\ICanBoogie\Core\RunEvent $event, \ICanBoogie\Core $target) {

	$target->site = $site = Model::find_by_request($event->request);
	$target->locale = $site->language;

	if ($site->timezone)
	{
		$target->timezone = $site->timezone;
	}

	$path = $site->path;

	if ($path)
	{
		Routing\Helpers::patch('contextualize', function ($str) use ($path)
		{
			return $path . $str;
		});

		Routing\Helpers::patch('decontextualize', function ($str) use ($path)
		{
			if (strpos($str, $path . '/') === 0)
			{
				$str = substr($str, strlen($path));
			}

			return $str;
		});
	}
});
```





### Request dispatchers are collected

The `ICanBoogie\HTTP\Dispatcher::collect` event of class [ICanBoogie\HTTP\Dispatcher\CollectEvent](http://icanboogie.org/docs/class-ICanBoogie.HTTP.Dispatcher.CollectEvent.html)
is fired when dispatchers are collected, just before the main dispatcher is instantiated.

Third parties may use this event to register dispatchers or alter dispatchers.

The following code illustrate how a `hello` dispatcher, that returns
"Hello world!" when the request matches the path "/hello", can be registered.

```php
<?php

use ICanBoogie\HTTP\Dispatcher;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;

$core->events->attach(function(Dispatcher\CollectEvent $event, Dispatcher $target) {

	$event->dispatchers['hello'] = function(Request $request) {

		if ($request->path === '/hello')
		{
			return new Response('Hello world!');
		}
	}

});
```





## Getting started





### Configuring

Low-level components of the framework are configured using configuration files. The default
configuration files are available in the `/config/` folder. To override the configuration or part
of it, you can provide the path or paths to your own configuration files.

For instance, configuring the _core_ instance:

1\. Edit your _core_ configuration file e.g. `/protected/all/config/core.php` with the following
lines:

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

2\. Define the config folder of your application in the `icanboogie.json` file:

```json
{
	"config-path": "protected/all/config/"
}
```

3\. Create the _core_ object:

```php
<?php

$core = new ICanBoogie\Core( ICanBoogie\get_autoconfig() );
```




### Running the Core and returning a response

Before we can process requests we need to run the framework, which indexes modules and select
the context for the application.

Although they are usually defined in configuration fragments, once the framework is running we
can just as easily add routes or attach events.

Finally we can execute the initial HTTP request and return its response.

```php
<?php

// index.php

# Running the core returns the initial request.

$request = $core();

# Here we could add routes or attach events but we'll just execute the
# initial request, obtain a response from it and return that response.

$response = $request();
$response();
```





----------





## Requirements

The minimum requirement is PHP 5.4.

ICanBoogie has been tested with Apache HTTP server on Linux, MacOS and Windows operating systems.
The Apache server must support URL rewriting.





## Installation

The recommended way to install this package is through [Composer](http://getcomposer.org/).
Create a `composer.json` file and run `php composer.phar install` command to install it:

```json
{
	"minimum-stability": "dev",

	"require":
	{
		"icanboogie/icanboogie": "2.x"
	},

	"scripts": {
		"post-autoload-dump": "ICanBoogie\\AutoConfig\\Generator::on_autoload_dump"
	}
}
```

The _script_ section is required in the _root_ package to enable the _auto-config_ feature.

The following packages are required, you might want to check them out:

- [icanboogie/common](https://github.com/ICanBoogie/Common)
- [icanboogie/inflector](https://github.com/ICanBoogie/Inflector)
- [icanboogie/datetime](https://github.com/ICanBoogie/DateTime)
- [icanboogie/prototype](https://github.com/ICanBoogie/Prototype)
- [icanboogie/activerecord](https://github.com/ICanBoogie/ActiveRecord)
- [icanboogie/event](https://github.com/ICanBoogie/Event)
- [icanboogie/http](https://github.com/ICanBoogie/HTTP)
- [icanboogie/routing](https://github.com/ICanBoogie/Routing)
- [icanboogie/operation](https://github.com/ICanBoogie/Operation)
- [icanboogie/errors](https://github.com/ICanBoogie/Errors)
- [icanboogie/module](https://github.com/ICanBoogie/Module)

The following packages can also be installed for additionnal features:

- [icanboogie/activerecord](https://github.com/ICanBoogie/ActiveRecord): ActiveRecord Object-relational mapping.,
- [icanboogie/cldr](https://github.com/ICanBoogie/CLDR): Provides internationalization for
your application
- [icanboogie/i18n](https://github.com/ICanBoogie/I18n): Provides localization for your application
and nice internationalization helpers.
- [icanboogie/image](https://github.com/ICanBoogie/Image): Provides image resizing, filling,
and color resolving.





### Cloning the repository

The package is [available on GitHub](https://github.com/ICanBoogie/ICanBoogie), its repository can be
cloned with the following command line:

	$ git clone git://github.com/ICanBoogie/ICanBoogie.git





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

[![Build Status](https://travis-ci.org/ICanBoogie/ICanBoogie.svg?branch=2.0)](https://travis-ci.org/ICanBoogie/ICanBoogie)





## License

ICanBoogie is licensed under the New BSD License - See the [LICENSE](LICENSE) file for details.





[Composer]: http://getcomposer.org/
[Core]: http://icanboogie.org/docs/class-ICanBoogie.Core.html
[DateTime]: http://icanboogie.org/docs/class-ICanBoogie.DateTime.html
[TimeZone]: http://icanboogie.org/docs/class-ICanBoogie.TimeZone.html
[Object]: http://icanboogie.org/docs/class-ICanBoogie.Object.html
[Prototype package]: https://github.com/ICanBoogie/Prototype
[Request]: http://icanboogie.org/docs/class-ICanBoogie.HTTP.Request.html