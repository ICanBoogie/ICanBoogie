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

class Config
{
	protected $packages;
	protected $destination;
	protected $filesystem;
	protected $validator;
	protected $fragments = [];
	protected $weights = [];

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
			list($package, $pathname) = $pi;

			$pathname = realpath($pathname);
			$weight = -10;

			if ($package instanceof RootPackage)
			{
				$weight = 10;
			}

			$fragment = $this->resolve_fragment($package, $pathname);

			if (!$fragment)
			{
				continue;
			}

			$fragments[$pathname] = $fragment;
			$weights[$pathname] = $weight;
		}

		return [ $fragments, $weights ];
	}

	/**
	 * Resolve the autoconfig fragment of a package.
	 *
	 * @param Package $package Package.
	 * @param string $pathname The pathname to the package.
	 *
	 * @return mixed|null The autoconfig fragment, or `null` if the package doesn't define one.
	 */
	protected function resolve_fragment(Package $package, $pathname)
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
	public function synthesize(Filesystem $filesystem=null)
	{
		if (!$filesystem)
		{
			$filesystem = $this->filesystem;
		}

		$config = [

			'config-constructor' => [],
			'config-path' => [],
			'locale-path' => [],
			'module-path' => [],
			'autoconfig-filters' => [],
			'app-root' => 'protected',
			'app-paths' => []

		];

		foreach ($this->fragments as $path => $fragment)
		{
			foreach ($fragment as $key => $value)
			{
				switch ($key)
				{
					case 'config-constructor':

						$config[$key] = array_merge($config[$key], $value);

						break;

					case 'config-path':

						foreach ((array) $value as $v)
						{
							$config[$key][] = [

								$filesystem->findShortestPathCode($this->destination, "$path/$v"),
								$this->weights[$path]

							];
						}

						break;

					case 'locale-path':
					case 'module-path':

						foreach ((array) $value as $v)
						{
							$config[$key][] = $filesystem->findShortestPathCode($this->destination, "$path/$v");
						}

						break;

					case 'autoconfig-filters':

						$config[$key] = array_merge($config[$key], (array) $value);

						break;

					case 'app-root':

						$config[$key] = $value;

						break;

					case 'app-paths':

						$config[$key] = array_merge($config[$key], (array) $value);

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
	public function render($synthesized_config=null)
	{
		if (!$synthesized_config)
		{
			$synthesized_config = $this->synthesize();
		}

		$class = __CLASS__;

		$config_constructor = $this->render_config_constructor($synthesized_config['config-constructor']);
		$config_path = $this->render_config_path($synthesized_config['config-path']);
		$locale_path = implode(",\n\t\t", $synthesized_config['locale-path']);
		$module_path = implode(",\n\t\t", $synthesized_config['module-path']);
		$filters = $this->render_filters($synthesized_config['autoconfig-filters']);
		$app_root = $synthesized_config['app-root'];
		$app_paths = implode(",\n\t\t", $synthesized_config['app-paths']);

		return <<<EOT
<?php

// autoconfig.php @generated by $class

return [

	'config-constructor' => [

		$config_constructor

	],

	'config-path' => [

		$config_path

	],

	'locale-path' => [

		$locale_path

	],

	'module-path' => [

		$module_path

	],

	'filters' => [

		$filters

	],

	'root' => dirname(dirname(__DIR__)),

	'app-root' => "$app_root",

	'app-paths' => [

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

			echo "Created autoconfig in {$this->destination}\n";
		}
		catch (\Exception $e)
		{
			echo $e;

			throw $e;
		}
	}
}
