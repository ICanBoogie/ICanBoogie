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
use ICanBoogie\Accessor\AccessorTrait;

/**
 * @codeCoverageIgnore
 *
 * @property-read Package[] $packages
 */
class AutoconfigGenerator
{
	use AccessorTrait;

	/**
	 * @var Package[]
	 */
	private $packages;

	/**
	 * @return array<string, Package>|\Generator
	 */
	protected function get_packages()
	{
		foreach ($this->packages as list($package, $pathname))
		{
			if (!$pathname)
			{
				$pathname = getcwd();
			}

			yield $pathname => $package;
		}

		return null;
	}

	/**
	 * @var string
	 */
	private $destination;

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
	 * @var ExtensionAbstract[]
	 */
	private $extensions = [];

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

		/* @var $package Package */
		/* @var $pathname string */

		foreach ($packages as list($package, $pathname))
		{
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
		if (isset($fragment[ComposerExtra::CONFIG_WEIGHT]))
		{
			return $fragment[ComposerExtra::CONFIG_WEIGHT];
		}

		if ($package instanceof RootPackage)
		{
			return Autoconfig::CONFIG_WEIGHT_APP;
		}

		return Autoconfig::CONFIG_WEIGHT_FRAMEWORK;
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
		# It seems `$pathname` can be empty when `composer install` is run for the first time,
		# in which case we use the current directory.
		#

		if (!$pathname) {
			$pathname = getcwd();
		}

		#
		# Trying "extra/icanboogie" in "composer.json".
		#
		# We read the JSON file ourselves because $package->getExtra() can't be trusted for some
		# reason.
		#

		$composer_pathname = $pathname . DIRECTORY_SEPARATOR . 'composer.json';

		if (!file_exists($composer_pathname)) {
			trigger_error("Missing file: `$composer_pathname`.", E_USER_NOTICE);
			return null;
		}

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
	 * @return array
	 */
	private function synthesize()
	{
		static $mapping = [

			ComposerExtra::CONFIG_CONSTRUCTOR => Autoconfig::CONFIG_CONSTRUCTOR,
			ComposerExtra::CONFIG_PATH => Autoconfig::CONFIG_PATH,
			ComposerExtra::LOCALE_PATH => Autoconfig::LOCALE_PATH,
			ComposerExtra::AUTOCONFIG_FILTERS => Autoconfig::AUTOCONFIG_FILTERS,
			ComposerExtra::APP_PATHS => Autoconfig::APP_PATHS,

		];

		$filesystem = $this->filesystem;

		$config = [

			Autoconfig::CONFIG_CONSTRUCTOR => [],
			Autoconfig::CONFIG_PATH => [],
			Autoconfig::LOCALE_PATH => [],
			Autoconfig::AUTOCONFIG_FILTERS => [],
			Autoconfig::APP_PATHS => []

		];

		$extensions = [];

		foreach ($this->fragments as $path => $fragment)
		{
			foreach ($fragment as $key => $value)
			{
				switch ($key)
				{
					case ComposerExtra::CONFIG_CONSTRUCTOR:
					case ComposerExtra::AUTOCONFIG_FILTERS:
					case ComposerExtra::APP_PATHS:

						$key = $mapping[$key];
						$config[$key] = array_merge($config[$key], (array) $value);

						break;

					case ComposerExtra::CONFIG_PATH:

						foreach ((array) $value as $v)
						{
							$config[Autoconfig::CONFIG_PATH][] = [

								$filesystem->findShortestPathCode($this->destination, "$path/$v"),
								$this->weights[$path]

							];
						}

						break;

					case ComposerExtra::LOCALE_PATH:

						$key = $mapping[$key];

						foreach ((array) $value as $v)
						{
							$config[$key][] = $filesystem->findShortestPathCode($this->destination, "$path/$v");
						}

						break;

					case ComposerExtra::AUTOCONFIG_EXTENSION:

						$extensions[] = $value;
				}
			}
		}

		$this->extensions = array_map(function ($extension) {

			return new $extension($this);

		}, $extensions);

		foreach ($this->extensions as $extension)
		{
			$extension->synthesize($config);
		}

		return $config;
	}

	/**
	 * @param string $to
	 *
	 * @return string
	 */
	public function findShortestPathCode($to)
	{
		return $this->filesystem->findShortestPathCode($this->destination, $to);
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

		$config_constructor = $this->render_config_constructor($synthesized_config[Autoconfig::CONFIG_CONSTRUCTOR]);
		$config_path = $this->render_config_path($synthesized_config[Autoconfig::CONFIG_PATH]);
		$locale_path = implode(",\n\t\t", $synthesized_config[Autoconfig::LOCALE_PATH]);
		$filters = $this->render_filters($synthesized_config[Autoconfig::AUTOCONFIG_FILTERS]);
		$app_paths = implode(",\n\t\t", $synthesized_config[Autoconfig::APP_PATHS]);

		$extension_render = '';

		foreach ($this->extensions as $extension)
		{
			$extension_render .= "\n" . $extension->render() . "\n";
		}

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

	Autoconfig::AUTOCONFIG_FILTERS => [

		$filters

	],

	Autoconfig::APP_PATH => getcwd() . DIRECTORY_SEPARATOR . Autoconfig::DEFAULT_APP_DIRECTORY,

	Autoconfig::APP_PATHS => [

		$app_paths

	],

	Autoconfig::BASE_PATH => getcwd(),
$extension_render
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
