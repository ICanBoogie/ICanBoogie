ICanBoogie [![Build Status](https://secure.travis-ci.org/ICanBoogie/ICanBoogie.png?branch=master)](http://travis-ci.org/ICanBoogie/ICanBoogie)
==========

__ICanBoogie__ is a high-performance object-oriented framework for PHP 5.3+. It is written
with speed, flexibility and lightness in mind. ICanBoogie doesn't try to be an all-in-one do-it-all
solution but provides the essential classes and logic to build web applications.

ICanBoogie packages offers the following features: Prototypes, ActiveRecords, Internationalization,
Modules, a RESTful API, Request/Dispatch/Response, Operations, Events, Hooks, Sessions, Routes,
Caching and more.

Together with [Brickrouge](http://brickrouge.org) and Patron, ICanBoogie is one of the
components that make the CMS [Icybee](http://icybee.org). You might want to check these
projects too.




### Acknowledgement

[MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org),
[Yii](http://www.yiiframework.com), and of course [Bacara](http://www.youtube.com/watch?v=KGuFn0RPgaE).


### Requirements

The minimum requirement for the ICanBoogie framework is PHP5.3. ICanBoogie has been tested with
Apache HTTP server on Linux, MacOS and Windows operating systems. The Apache server must support
URL rewriting.




## Installation

The recommended way to install ICanBoogie is [through composer](http://getcomposer.org/). Create a
`composer.json` file and run `php composer.phar install` command to install it:

```
{
    "minimum-stability": "dev",
    "require": {
        "icanboogie/icanboogie": "1.0.*"
    }
}
```

## Tests

To run the test suite, you need [composer](http://getcomposer.org/) and [PHPUnit](http://www.phpunit.de/manual/current/en/).
First install the required packages with the `make install` command, then run the test suite
with `make test`.




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

require_once 'ICanBoogie.phar';

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




## Licence

ICanBoogie is licensed under the New BSD License - See the LICENSE file for details.