# ICanBoogie [![Build Status](https://travis-ci.org/ICanBoogie/ICanBoogie.png?branch=master)](https://travis-ci.org/ICanBoogie/ICanBoogie)

__ICanBoogie__ is a high-performance framework for PHP 5.3+. It is written with speed, flexibility
and lightness in mind. ICanBoogie doesn't try to be an all-in-one do-it-all solution but provides
the essential classes and logic to build web applications.

ICanBoogie packages offers the following features: Prototypes, ActiveRecords, Internationalization,
Modules, a RESTful API, Request/Dispatch/Response/Rescue, Operations, Events, Hooks, Sessions,
Routes, Caching and more.

Together with [Brickrouge](http://brickrouge.org) and Patron, ICanBoogie is one of the
components that make the CMS [Icybee](http://icybee.org). You might want to check these
projects too.





### Acknowledgement

[MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org),
[Yii](http://www.yiiframework.com), and of course [Bacara](http://www.youtube.com/watch?v=KGuFn0RPgaE).





## Working with ICanBoogie

ICanBoogie tries to leverage the magic features of PHP as much as possible. For instance, magic
setters and getters, invokable objects, collections are arrays, objects as strings.

Applications created with ICanBoogie often have a very simple and fluid code flow.





### Magic getters and setters

Magic properties are used in favour of getter and setter methods (e.g. `getXxx()` or `setXxx()`).
For example, `DateTime` instances provide a `minute` magic property in favour of `getMinute()` and
`setMinute()` methods:

```php
<?php

$time = new ICanBoogie\DateTime('2013-05-17 12:30:45', 'utc');
echo $time;         // 2013-05-17T12:30:45Z
echo $time->minute; // 30

$time->minute += 120;

echo $time;         // 2013-05-17T14:30:45Z
```




### Invokable objects

Objects performing a main action are invoked to perform that action. For instance, a prepared
database statement whose main purpose is to query the database don't have an `execute()` method.
It is invoked to perform its purpose:

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

Collections of objects are always managed as arrays, wheter they are records in the database,
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
// SELECT * FROM `pages` `page` INNER JOIN `nodes` `node` USING(`nid`)  WHERE (`constructor` = ?) AND (`is_online` = ?) AND (siteid = 0 OR siteid = ?) AND (language = "" OR language = ?) AND (`nid` = ?) ORDER BY created_on DESC LIMIT 5
```





## Getting started





### Configuring

Low-level components of the framework are configured using configuration files. The default
configuration files are available in the `/config/` folder. To override the configuration or part
of it, you can provide the path or paths to your own configuration files.

For instance, defining the primary database connection:

1. Edit your _core_ configuration file e.g. `/protected/all/config/core.php` with the following
lines:

```php
<?php

return array
(
	'connections' => array
	(
		'primary' => array
		(
			'dsn' => 'mysql:dbname=<databasename>;host=<hostname>',
			'username' => '<username>',
			'password' => '<password>'
		)
	)
);
```

2. Then specify your config path while creating the _core_ object:

```php
<?php

namespace ICanBoogie;

$core = new Core
(
	array
	(
		'config paths' => array(DOCUMENT_ROOT . 'protected/all/'),
		'locale paths' => array(DOCUMENT_ROOT . 'protected/all/')
	)
);
```





### Running

Before we can process requests we need to run the framework. Running the framework indexes
modules and select the context of the application.

When the framework is running we can add routes or attach events, although they are usually
defined in configuration fragments.

Finally we can execute the HTTP request and return a response.

```php
<?php

$core();

# here we could add routes or attach events

$request = $core->initial_request;
$response = $request();
$response();
```





## Events





### The core is running

The `ICanBoogie\Core::run` event of class [ICanBoogie\Core\RunEvent](http://icanboogie.org/docs/class-ICanBoogie.Core.RunEvent.html)
is fired when the core is running.

Third parties may use this event to alter various states of the application, starting with the
initial request. 


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





### The main dispatcher is instantiated

The `ICanBoogie\HTTP\Dispatcher::collect` event of class [ICanBoogie\HTTP\Dispatcher\CollectEvent](http://icanboogie.org/docs/class-ICanBoogie.HTTP.Dispatcher.CollectEvent.html)
is fired when the dispatcher is instantiated.

Third parties may use this event to register dispatchers or alter dispatchers.

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





## Requirements

The minimum requirement is PHP5.3. ICanBoogie has been tested with Apache HTTP server on Linux,
MacOS and Windows operating systems. The Apache server must support URL rewriting.





## Installation

The recommended way to install this package is through [composer](http://getcomposer.org/).
Create a `composer.json` file and run `php composer.phar install` command to install it:

```json
{
	"minimum-stability": "dev",
	"require":
	{
		"icanboogie/icanboogie": "*"
	}
}
```





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

[![Build Status](https://travis-ci.org/ICanBoogie/ICanBoogie.png?branch=master)](https://travis-ci.org/ICanBoogie/ICanBoogie)





## License

ICanBoogie is licensed under the New BSD License - See the LICENSE file for details.