Configuration bas-niveau du framework ICanBoogie et de ses composants
=====================================================================

Les fonctionnalités bas-niveau du [framework ICanBoogie](https://github.com/ICanBoogie/ICanBoogie)
et de ses composants sont configurées à l'aide de fichiers PHP. Chaque configuration peut être
définie par un ensemble de fichiers que l'on appelle "fragments", et qui peuvent se trouver à de
multiples endroits sur le serveur. Ces fragments sont utilisés pour synthétiser la configuration
d'un composant ou de plusieurs composants, on parlera dans ce cas d'une configuration _dérivée_.


## Description d'un fragment de configuration

Un fragment de configuration est un fichier PHP dont le code renvoie un tableau associatif, il se
trouve dans un dossier "config" et porte généralement le nom de la configuration qu'il permet de
synthétiser. De multiples fragments sont utilisés pour synthétiser une configuration ou une
configuration dérivée, pour la configuration "core" on pourra les trouver aux endroits
suivants :

* /icybee/framework/icanboogie/config/core.php
* /icybee/config/core.php
* /protected/all/config/core.php

Voici les extraits de ces trois fragments tels qu'ils sont définis pour le site du
[CMS Publishr](http://www.wdpublisher.com), en commençant par celui définit par le framework
ICanBoogie :

	```php
	$core = $path . 'lib/core/';
	...
	return array
	(
		'autoload' => array
		(
			'ICanBoogie\Accessor\Configs' => $core . 'accessor/configs.php',
			'ICanBoogie\Accessor\Connections' => $core . 'accessor/connections.php',
			'ICanBoogie\Accessor\Models' => $core . 'accessor/models.php',
			...
		),
		
		'cache configs' => false,
		'cache modules' => false,
		'cache catalogs' => false,
		
		'config constructors' => array
		(
			'debug' => array('ICanBoogie\Debug::synthesize_config'),
			'methods' => array('ICanBoogie\Object::synthesize_methods', 'hooks')
		),
		
		'connections' => array
		(
	
		),
		
		...
	);
	```
	
Voici un extrait du fragment tel qu'il est définit par le CMS :

	```php
	return array
	(
		'autoload' => array
		(
			'Icybee\Accessor\Modules' => $path . 'lib/core/accessor/modules.php',
			'Icybee\Operation\ActiveRecord\Save' => $path . 'lib/operation/activerecord/save.php',
			'Icybee\Operation\ActiveRecord\Delete' => $path . 'lib/operation/activerecord/delete.php',
			...
		),
		
		'connections' => array
		(
			'local' => array
			(
				'dsn' => 'sqlite:' . ICanBoogie\DOCUMENT_ROOT . 'repository/lib/local.sqlite'
			)
		),
	
		'modules' => array
		(
			$path . 'modules'
		)
	);
	```

Enfin, voici un extrait du fragment tel qu'il est définit pour le site :

	```php
	return array
	(
		'cache assets' => false,
		'cache catalogs' => true,
		'cache configs' => true,
		'cache modules' => true,
	
		'connections' => array
		(
			'primary' => array
			(
				'dsn' => 'mysql:dbname=publishr',
				'username' => '***',
				'password' => '***',
				'options' => array
				(
					'#prefix' => 'wdp'
				)
			)
		),
	
		'modules' => array
		(
			$path . 'modules'
		)
	);
	```
	
De nombreux fragments de configuration sont également définis par les modules, ils seront chargés
par la suite mais ne pourront supplanter les fragements de configuration du site, qui ont plus
d'importance.


## Synthétiser une configuration
	
La méthode `synthesize` de l'accesseur `configs` permet de synthétiser une configuration. Dans
l'exemple ci-dessous la fonction `synthesize` est utilisée comme fonction de rappel pour
synthétiser la configuration "core", on qualifiera une telle fonction de _constructeur_ :

	```php
	function synthesize($fragments)
	{
		return call_user_func_array('wd_array_merge_recursive', $fragments);
	}

	$config = $core->configs->synthesize('core', 'synthesize');
	```


### Constructeurs magiques

La méthode `synthesize` offre deux constructeurs magiques : "merge" et "recursive merge". Le
premier revient à utiliser la fonction `array_merge()` avec tous les fragments pour arguments,
le second revient à utiliser la fonction `wd_array_merge_recursive()` avec tous les fragments,
pour aguments comme le fait la fonction `synthesize()` dans l'exemple ci-dessus. Pour un résultat
équivalent, on pourrait donc écrire le code suivant :

	```php
	$config = $core->configs->synthesize('core', 'recursive merge');
	```

En définissant les constructeurs de configuration dans la configuration "core", il est possible
d'obtenir une configuration encore plus simplement.


## Synthétiser une configuration en utilisant un constructeur 

Les constructeurs de configuration définis dans le tableau associatif `config constructors` de la
configuration "core" ajoutent un peu de magie à la synthése des configurations et simplifient
l'obtention des configurations.

Voici un extrait des constructeurs définis par le
[framework ICanBoogie](https://github.com/ICanBoogie/ICanBoogie) :

	```php
	return array
	(
		...
		
		'config constructors' => array
		(
			'debug' => array('ICanBoogie\Debug::synthesize_config'),
			'methods' => array('ICanBoogie\Object::synthesize_methods', 'hooks')
		)
		
		...
	);
	```

Par rapport à la méthode précédente cela permet de récupérer la configuration de la façon
suivante :

	```php
	$config = $core->configs['core'];
	```
	

## Synthétiser une configuration à partir des fragments d'une autre configuration

Il est également possible de synthétiser une configuration à partir des fragements d'une autre
configuration, on parlera alors d'un _dérivé_ de configuration.

La configuration "methods" dont nous avons précédement vu le constructeur est dérivée de la
configuration "hooks", elle utilise les fragments de cette configuration pour créer une
configuration qui lui est propre, filtrant les fonctions de rappel définies dans les
fragments pour extraitre celles qui concernent l'ajout de méthodes aux instances de la classe
[Object](https://github.com/ICanBoogie/ICanBoogie/blob/master/lib/core/object.php) et de celles
qui en héritent.

On définit la configuration source en utilisant un argument supplémentaire :

	```php
	$methods = $core->configs->synthesize('methods', 'ICanBoogie\Object::synthetize_methods', 'hooks');
	```

Même chose pour le constructeur :

	```php
	'config constructors' => array
	(
		'methods' => array('ICanBoogie\Object::synthetize_methods', 'hooks')
	)
	```

Quelque soit la méthode utilisée les configurations sont synthétisées à la demande et sont
réutilisées lorsqu'elles sont de nouveau demandées. Il est possible d'accélérer le processus
en activant la mise en cache des configurations synthétisées.

	
## La mise en cache des configurations synthétisées

La mise en cache des configurations synthétisées permet de supprimer le cout de la synthétisation
des configurations en réutilisant des configurations précédément synthétisées. On active
la mise en cache depuis la configuration "core", on préférera utiliser celle du site
(/protected/all/config/core.php) :

	```php
	return array
	(
		...
		
		'cache configs' => true
	);
	```

La configuration "core" est particulière en cela qu'elle ne peut être mise en cache, cependant
d'autres mécanismes permettent la mise en cache des fragments définis par les modules pour cette
configuration.