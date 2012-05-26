ICanBoogie
==========

__ICanBoogie__ is a high-performance object-oriented framework for PHP 5.3.6+. It is written
with speed, flexibility and lightness in mind. ICanBoogie doesn't try to be an all-in-one do-it-all
solution, prefering to provided a small but strong set of classes and logics as a solid ground to
build web applications.

ICanBoogie offers the following features: ActiveRecords, Internationalization, Modules,
a RESTful API, Request/Dispatch/Response, runtime Mixins, Autoload, Operations, Events, Hooks,
Sessions, Routes, Caching and more.

Together with [Brickrouge](http://brickrouge.org) and Patron, ICanBoogie is one of the
components that make the CMS [Icybee](http://icybee.org). You might want to check these
projects too.

ICanBoogie is a fork of the [WdCore](https://github.com/olvlvl/WdCore) framework made to take
advantage of the many features brought by PHP5.3.

*Inspiration*: [MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org),
[Yii](http://www.yiiframework.com) and of course [Bacara](http://www.youtube.com/watch?v=KGuFn0RPgaE) :)



Getting started
---------------

### Requirements

The minimum requirement for the ICanBoogie framework is PHP5.3.6. ICanBoogie has been tested with
Apache HTTP server on Linux, MacOS and Windows operating systems. The Apache server must support
URL rewriting.


### Installation

Clone ICanBoogie from its GitHub repository using the following command:

	$ git clone git@github.com:ICanBoogie/ICanBoogie.git

ICanBoogie doesn't need to be web-accessible, thus a single instance can be used to power multiple
projects.


### Configuring

Low-level components of the framework are configured using multiple configuration files, usually
one per component. The default configuration files are available in the `/config/` folder. To
override the configuration or part of it, you can provide the path or paths to your configuration
files.

For example, you want to define the primary database connection:

1. Edit your _core_ configuration file e.g. `/protected/all/config/core.php` with the following
lines:

	```php
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
	namespace ICanBoogie;

	require_once 'ICanBoogie.phar';

	$core = new Core
	(
		array
		(
			'paths' => array
			(
				'config' => array(DOCUMENT_ROOT . 'protected/all/'),
				'locale' => array(DOCUMENT_ROOT . 'protected/all/')
			)
		)
	);
	```

### Running

Before we can process requests we need to run the framework. Running the framework indexes
modules and select the context of the application.

When the framework is running we can add additionnal routes or attach additionnal events,
for they are usually defined in config fragements.

Finally we can execute the HTTP request and return a response.

	```php
	$core->run();

	# here we could add routes or attach events

	$request = HTTP\Request::from($_SERVER);
	$response = $request();
	$response();
	```