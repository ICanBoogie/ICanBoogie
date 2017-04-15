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
final class AutoconfigGenerator
{
	use AccessorTrait;

	/**
	 * @var Package[]
	 */
	private $packages;

	/**
	 * @return \Generator
	 */
	private function get_packages()
	{
		foreach ($this->packages as list($package, $pathname))
		{
			if (!$pathname)
			{
				$pathname = getcwd();
			}

			yield $pathname => $package;
		}
	}

	/**
	 * @var string
	 */
	private $destination;

	/**
	 * @var Schema
	 */
	private $composer_schema;

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var array
	 */
	private $fragments = [];

	/**
	 * @var array
	 */
	private $weights = [];

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
		$this->filesystem = new Filesystem;
	}

	/**
	 * Search for autoconfig fragments defined by the packages and create the autoconfig file.
	 */
	public function __invoke()
	{
		list($fragments, $weights) = $this->collect_fragments();

		$this->fragments = $fragments;
		$this->weights = $weights;

		$this->write();
	}

	/**
	 * @param string $to
	 *
	 * @return string
	 *
	 * @used-by ExtensionAbstract
	 */
	public function findShortestPathCode($to)
	{
		return $this->filesystem->findShortestPathCode($this->destination, $to);
	}

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return string
	 *
	 * @used-by ExtensionAbstract
	 */
	public function render_entry($key, $value)
	{
		return <<<EOT
    '$key' => $value,
EOT;
	}

	/**
	 * @param string $key
	 * @param array $items
	 * @param callable $renderer
	 *
	 * @return string
	 *
	 * @used-by ExtensionAbstract
	 */
	public function render_array_entry($key, array $items, callable $renderer)
	{
		$rendered_items = implode(array_map(function ($item, $key) use ($renderer) {

			return "\t\t" . $renderer($item, $key) . ",\n";

		}, $items, array_keys($items)));

		/* @var string $key */
		/* @var string $rendered_items */

		return <<<EOT
    '$key' => [

$rendered_items
    ],
EOT;
	}

	/**
	 * Collect autoconfig fragments from packages.
	 *
	 * @return array An array with the collected fragments and their weights.
	 */
	private function collect_fragments()
	{
		$fragments = [];
		$weights = [];

		foreach ($this->get_packages() as $pathname => $package)
		{
			$pathname = realpath($pathname);
			$fragment = $this->find_fragment($pathname);

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
	 * Try to find `extra/icanboogie` in package's `composer.json`.
	 *
	 * @param string $pathname The pathname to the package.
	 *
	 * @return array|null The autoconfig fragment, or `null` if the package doesn't define one.
	 */
	private function find_fragment($pathname)
	{
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

		$data = (new JsonFile($composer_pathname))->read();

		if (empty($data['extra']['icanboogie']))
		{
			return null;
		}

		return $data['extra']['icanboogie'];
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

								$this->findShortestPathCode("$path/$v"),
								$this->weights[$path]

							];
						}

						break;

					case ComposerExtra::LOCALE_PATH:

						$key = $mapping[$key];

						foreach ((array) $value as $v)
						{
							$config[$key][] = $this->findShortestPathCode("$path/$v");
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
	 * Render the synthesized autoconfig into a string.
	 *
	 * @param array $config Synthesized config.
	 *
	 * @return string
	 */
	private function render($config = [])
	{
		if (!$config)
		{
			$config = $this->synthesize();
		}

		$class = __CLASS__;

		$rendered_entries = [

			$this->render_entry(
				Autoconfig::BASE_PATH,
				'getcwd()'
			),

			$this->render_entry(
				Autoconfig::APP_PATH,
				'getcwd() . DIRECTORY_SEPARATOR . "' . Autoconfig::DEFAULT_APP_DIRECTORY . '"'
			),

			$this->render_app_paths($config),
			$this->render_locale_paths($config),
			$this->render_config_constructor($config),
			$this->render_filters($config),
			$this->render_config_path($config),

		];

		foreach ($this->extensions as $extension)
		{
			$rendered_entries[] = $extension->render();
		}

		$extension_render = implode(array_map(function ($rendered_entry) {
			return "\n{$rendered_entry}\n";
		}, $rendered_entries));

		return <<<EOT
<?php

/*
 * DO NOT EDIT THIS FILE
 *
 * @generated by $class
 * @see https://icanboogie.org/docs/4.0/autoconfig
 */ 

return [
$extension_render
];
EOT;
	}

	/**
	 * Render the {@link Autoconfig::APP_PATHS} part of the autoconfig.
	 *
	 * @param array $config
	 *
	 * @return string
	 */
	private function render_app_paths(array $config)
	{
		return $this->render_array_entry(
			Autoconfig::APP_PATHS,
			$config[Autoconfig::APP_PATHS],
			function ($item)
			{
				return (string) $item;
			}
		);
	}

	/**
	 * Render the {@link Autoconfig::LOCALE_PATH} part of the autoconfig.
	 *
	 * @param array $config
	 *
	 * @return string
	 */
	private function render_locale_paths(array $config)
	{
		return $this->render_array_entry(
			Autoconfig::LOCALE_PATH,
			$config[Autoconfig::LOCALE_PATH],
			function ($item)
			{
				return (string) $item;
			}
		);
	}

	/**
	 * Render the {@link Autoconfig::CONFIG_CONSTRUCTOR} part of the autoconfig.
	 *
	 * @param array $config
	 *
	 * @return string
	 */
	private function render_config_constructor(array $config)
	{
		$synthesized = $config[Autoconfig::CONFIG_CONSTRUCTOR];
		ksort($synthesized);

		return $this->render_array_entry(
			Autoconfig::CONFIG_CONSTRUCTOR,
			$synthesized,
			function ($constructor, $name)
			{
				list($callback, $from) = explode('#', $constructor) + [ 1 => null ];

				return "'$name' => [ '$callback'" . ($from ? ", '$from'" : '') . " ]";
			}
		);
	}

	/**
	 * Render the {@link Autoconfig::CONFIG_PATH} part of the autoconfig.
	 *
	 * @param array $config
	 *
	 * @return string
	 */
	private function render_config_path(array $config)
	{
		return $this->render_array_entry(
			Autoconfig::CONFIG_PATH,
			$config[Autoconfig::CONFIG_PATH],
			function ($item)
			{
				list($path_code, $weight) = $item;

				return "{$path_code} => {$weight}";
			}
		);
	}

	/**
	 * Render the {@link Autoconfig::AUTOCONFIG_FILTERS} part of the autoconfig.
	 *
	 * @param array $config
	 *
	 * @return string
	 */
	private function render_filters(array $config)
	{
		return $this->render_array_entry(
			Autoconfig::AUTOCONFIG_FILTERS,
			$config[Autoconfig::AUTOCONFIG_FILTERS],
			function ($callable)
			{
				return "'$callable'";
			}
		);
	}

	/**
	 * Write the autoconfig file.
	 */
	private function write()
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
