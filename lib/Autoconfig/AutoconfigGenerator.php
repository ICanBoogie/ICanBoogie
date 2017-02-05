<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Autoconfig;

use Composer\Util\Filesystem;
use Composer\Json\JsonFile;
use Composer\Package\Package;
use Composer\Package\RootPackage;

/**
 * @codeCoverageIgnore
 */
class AutoconfigGenerator implements Autoconfig
{
	/**
	 * @var Package[]
	 */
	protected $packages;

	/**
	 * @var string
	 */
	protected $destination;

	/**
	 * @var Schema
	 */
	protected $composer_schema;

	/**
	 * @var Schema
	 */
	protected $icanboogie_schema;

	/**
	 * @var Filesystem
	 */
	protected $filesystem;

	/**
	 * @var array
	 */
	protected $fragments = [];

	/**
	 * @var array
	 */
	protected $weights = [];

	/**
	 * @param Package[] $packages
	 * @param string $destination
	 */
	public function __construct(array $packages, $destination)
	{
		$this->packages = $packages;
		$this->destination = $destination;

		$this->composer_schema = new Schema(__DIR__ . '/composer-schema.json');
		$this->icanboogie_schema = new Schema(__DIR__ . '/icanboogie-schema.json');
		$this->filesystem = new Filesystem;
	}

	/**
	 * Search for autoconfig fragments defined by the packages and create the autoconfig file.
	 */
	public function __invoke()
	{
		list($fragments, $weights) = $this->resolve_fragments($this->packages);

		$this->fragments = $fragments;
		$this->weights = $weights;

		$this->write();
	}

	/**
	 * Resolve the autoconfig fragments defined by the packages.
	 *
	 * @param array $packages
	 *
	 * @return array An array with the resolved fragments and their weights.
	 */
	protected function resolve_fragments(array $packages)
	{
		$fragments = [];
		$weights = [];

		foreach ($packages as $pi)
		{
			/* @var $package Package */
			list($package, $pathname) = $pi;

			$pathname = realpath($pathname);
			$fragment = $this->resolve_fragment($pathname);

			if (!$fragment)
			{
				continue;
			}

			$fragments[$pathname] = $fragment;
			$weights[$pathname] = $this->resolve_config_weight($package, $fragment);
		}

		return [ $fragments, $weights ];
	}

	/**
	 * Resolves config weight.
	 *
	 * @param Package $package
	 * @param array $fragment
	 *
	 * @return int
	 */
	private function resolve_config_weight(Package $package, array $fragment)
	{
		if (isset($fragment[self::CONFIG_WEIGHT]))
		{
			return $fragment[self::CONFIG_WEIGHT];
		}

		if ($package instanceof RootPackage)
		{
			return self::CONFIG_WEIGHT_APP;
		}

		return self::CONFIG_WEIGHT_FRAMEWORK;
	}

	/**
	 * Resolve the autoconfig fragment of a package.
	 *
	 * @param string $pathname The pathname to the package.
	 *
	 * @return mixed|null The autoconfig fragment, or `null` if the package doesn't define one.
	 */
	protected function resolve_fragment($pathname)
	{
		#
		# Trying "extra/icanboogie" in "composer.json".
		#
		# We read the JSON file ourselves because $package->getExtra() can't be trusted for some
		# reason.
		#

		$composer_pathname = $pathname . DIRECTORY_SEPARATOR . 'composer.json';

		$this->composer_schema->validate_file($composer_pathname);

		$json = new JsonFile($composer_pathname);
		$data = $json->read();

		if (!empty($data['extra']['icanboogie']))
		{
			return $data['extra']['icanboogie'];
		}

		#
		# Trying "icanboogie.json"
		#

		$icanboogie_pathname = $pathname . DIRECTORY_SEPARATOR . 'icanboogie.json';

		if (!file_exists($icanboogie_pathname))
		{
			return null;
		}

		$this->icanboogie_schema->validate_file($icanboogie_pathname);

		$json = new JsonFile($icanboogie_pathname);

		return $json->read();
	}

	/**
	 * Synthesize the autoconfig fragments into a single array.
	 *
	 * @param Filesystem $filesystem
	 *
	 * @return array
	 */
	public function synthesize(Filesystem $filesystem = null)
	{
		if (!$filesystem)
		{
			$filesystem = $this->filesystem;
		}

		$config = [

			self::CONFIG_CONSTRUCTOR => [],
			self::CONFIG_PATH => [],
			self::LOCALE_PATH => [],
			self::MODULE_PATH => [],
			self::AUTOCONFIG_FILTERS => [],
			self::APP_ROOT => self::DEFAULT_APP_ROOT,
			self::APP_PATHS => []

		];

		foreach ($this->fragments as $path => $fragment)
		{
			foreach ($fragment as $key => $value)
			{
				switch ($key)
				{
					case self::CONFIG_CONSTRUCTOR:
					case self::AUTOCONFIG_FILTERS:
					case self::APP_PATHS:

						$config[$key] = array_merge($config[$key], (array) $value);

						break;

					case self::CONFIG_PATH:

						foreach ((array) $value as $v)
						{
							$config[$key][] = [

								$filesystem->findShortestPathCode($this->destination, "$path/$v"),
								$this->weights[$path]

							];
						}

						break;

					case self::LOCALE_PATH:
					case self::MODULE_PATH:

						foreach ((array) $value as $v)
						{
							$config[$key][] = $filesystem->findShortestPathCode($this->destination, "$path/$v");
						}

						break;


					case self::APP_ROOT:

						$config[$key] = $value;

						break;
				}
			}
		}

		return $config;
	}

	/**
	 * Render the synthesized autoconfig into a string.
	 *
	 * @param string $synthesized_config
	 *
	 * @return string
	 */
	public function render($synthesized_config =null)
	{
		if (!$synthesized_config)
		{
			$synthesized_config = $this->synthesize();
		}

		$class = __CLASS__;

		$config_constructor = $this->render_config_constructor($synthesized_config[self::CONFIG_CONSTRUCTOR]);
		$config_path = $this->render_config_path($synthesized_config[self::CONFIG_PATH]);
		$locale_path = implode(",\n\t\t", $synthesized_config[self::LOCALE_PATH]);
		$module_path = implode(",\n\t\t", $synthesized_config[self::MODULE_PATH]);
		$filters = $this->render_filters($synthesized_config[self::AUTOCONFIG_FILTERS]);
		$app_root = $synthesized_config[self::APP_ROOT];
		$app_paths = implode(",\n\t\t", $synthesized_config[self::APP_PATHS]);

		return <<<EOT
<?php

// autoconfig.php @generated by $class

use ICanBoogie\Autoconfig\Autoconfig;

return [

	Autoconfig::CONFIG_CONSTRUCTOR => [

		$config_constructor

	],

	Autoconfig::CONFIG_PATH => [

		$config_path

	],

	Autoconfig::LOCALE_PATH => [

		$locale_path

	],

	Autoconfig::MODULE_PATH => [

		$module_path

	],

	Autoconfig::AUTOCONFIG_FILTERS => [

		$filters

	],

	Autoconfig::ROOT => getcwd(),

	Autoconfig::APP_ROOT => "$app_root",

	Autoconfig::APP_PATHS => [

		$app_paths

	]
];
EOT;
	}

	/**
	 * Render the `config-constructor` part of the autoconfig.
	 *
	 * @param array $synthesized
	 *
	 * @return string
	 */
	protected function render_config_constructor($synthesized)
	{
		$lines = [];

		ksort($synthesized);

		foreach ($synthesized as $name => $constructor)
		{
			list($callback, $from) = explode('#', $constructor) + [ 1 => null ];

			$lines[] = "'$name' => [ '$callback'" . ($from ? ", '$from'" : '') . " ]";
		}

		return implode(",\n\t\t", $lines);
	}

	/**
	 * Render the `config-path` part of the autoconfig.
	 *
	 * @param array $synthesized
	 *
	 * @return string
	 */
	protected function render_config_path($synthesized)
	{
		$lines = [];

		foreach ($synthesized as $data)
		{
			list($pathcode, $weight) = $data;

			$lines[] = "{$pathcode} => {$weight}";
		}

		return implode(",\n\t\t", $lines);
	}

	/**
	 * Render the `filters` part of the autoconfig.
	 *
	 * @param array $synthesized
	 *
	 * @return string
	 */
	protected function render_filters($synthesized)
	{
		$lines = [];

		foreach ($synthesized as $callable)
		{
			$lines[] = "'$callable'";
		}

		return implode(",\n\t\t", $lines);
	}

	/**
	 * Write the autoconfig file.
	 */
	public function write()
	{
		try
		{
			file_put_contents($this->destination, $this->render());

			echo "Created Autoconfig in {$this->destination}\n";
		}
		catch (\Exception $e)
		{
			echo $e;

			throw $e;
		}
	}
}
