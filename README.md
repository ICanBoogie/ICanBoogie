# ICanBoogie

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

$core->run();

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





## License

ICanBoogie is licensed under the New BSD License - See the LICENSE file for details.