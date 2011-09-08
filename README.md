ICanBoogie
==========

__ICanBoogie__ is a high-performance object-oriented framework for PHP 5.3 and above. It is written
with speed, flexibility and lightness in mind. ICanBoogie doesn't try to be an all-in-one do-it-all
solution, prefering to provided a tiny but strong set of classes and logics as a solid ground to
build web applications.

ICanBoogie offers the following features: ActiveRecords, Internationalization, Modules,
a RESTful API, runtime Mixins, Autoload, Operations, Events, Hooks, Sessions, Routes, Caching,
Image resizing...

Together with BrickRouge and Patron, ICanBoogie is the base framework for the Icybee CMS, you might
want to check these projects too.

ICanBoogie is a fork of the [WdCore](https://github.com/Weirdog/WdCore) framework made to take
advantage of the many features brought by PHP5.3.

*Inspiration*: [MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org),
[Yii](http://www.yiiframework.com)



Getting started
---------------

### Requirements

The minimum requirement for the ICanBoogie framework is PHP5.3.3. ICanBoogie has been tested with
Apache HTTP server on Linux, MacOS and Windows operating systems. The Apache server must support
URL rewriting.


### Installation

The ICanBoogie framework can be retrieved from the GitHub repository at the following URL:

	git@github.com:ICanBoogie/ICanBoogie.git

ICanBoogie doesn't need to be web-accessible, thus a single instance can be used power multiple
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
	$core = new ICanBoogie\Core
	(
		array
		(
			'paths' => array
			(
				'config' => array(ICanBoogie\DOCUMENT_ROOT . 'protected/all/')
			)
		)
	);
	```