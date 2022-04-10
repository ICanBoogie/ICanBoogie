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

use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Package\RootPackageInterface;
use Composer\Util\Filesystem;
use ICanBoogie\Accessor\AccessorTrait;
use Throwable;

use function array_keys;
use function array_map;
use function array_merge;
use function explode;
use function file_put_contents;
use function getcwd;
use function implode;
use function ksort;
use function realpath;

/**
 * @codeCoverageIgnore
 *
 * @property-read array<string, Package> $packages
 * @property-read RootPackageInterface $root_package
 */
final class AutoconfigGenerator
{
    use AccessorTrait;

    /**
     * @var Package[]
     */
    private array $packages;

    /**
     * @return array<string, Package>
     */
    private function get_packages(): iterable
    {
        foreach ($this->packages as [ $package, $pathname ]) {
            if (!$pathname) {
                $pathname = getcwd();
            }

            yield $pathname => $package;
        }
    }

    private function get_root_package(): ?RootPackageInterface
    {
        foreach ($this->packages as [ $package ]) {
            if ($package instanceof RootPackageInterface) {
                return $package;
            }
        }

        return null;
    }

    private string $destination;
    private Filesystem $filesystem;

    /**
     * @var array<string, mixed>
     */
    private array $fragments = [];

    /**
     * @var array<string, int>
     */
    private array $weights = [];

    /**
     * @var ExtensionAbstract[]
     */
    private array $extensions = [];

    /**
     * @param Package[] $packages
     *
     * @uses get_root_package
     */
    public function __construct(array $packages, string $destination)
    {
        $this->packages = $packages;
        $this->destination = $destination;
        $this->filesystem = new Filesystem();
    }

    /**
     * Search for autoconfig fragments defined by the packages and create the autoconfig file.
     *
     * @throws Throwable
     */
    public function __invoke(): void
    {
        [ $fragments, $weights ] = $this->collect_fragments();

        $this->fragments = $fragments;
        $this->weights = $weights;
        $this->extensions = $this->collect_extensions($fragments);

        $this->validate_fragments($fragments);
        $this->write();
    }

    public function find_shortest_path_code(string $to): string
    {
        return $this->filesystem->findShortestPathCode($this->destination, $to);
    }

    public function render_entry(string $key, string $value): string
    {
        return <<<EOT
    '$key' => $value,
EOT;
    }

    /**
     * @param array<string, mixed> $items
     */
    public function render_array_entry(string $key, array $items, callable $renderer): string
    {
        $rendered_items = implode(
            array_map(
                fn($item, $key) => "\t\t" . $renderer($item, $key) . ",\n",
                $items,
                array_keys($items)
            )
        );

        return <<<EOT
    '$key' => [

$rendered_items
    ],
EOT;
    }

    /**
     * Collect autoconfig fragments from packages.
     *
     * @return array{0: array<string, mixed>, 1: array<string, int>}
     *     An array with the collected fragments and their weights.
     */
    private function collect_fragments(): array
    {
        $fragments = [];
        $weights = [];

        foreach ($this->get_packages() as $pathname => $package) {
            $pathname = realpath($pathname);

            assert(is_string($pathname));

            $fragment = $this->find_fragment($package);

            if (!$fragment) {
                continue;
            }

            $fragments[$pathname] = $fragment;
            $weights[$pathname] = $this->resolve_config_weight($package, $fragment);
        }

        return [ $fragments, $weights ];
    }

    /**
     * Try to find autoconfig fragment of package.
     *
     * @return array<string, mixed>|null The autoconfig fragment, or `null` if the package doesn't define one.
     */
    private function find_fragment(PackageInterface $package): ?array
    {
        return $package->getExtra()['icanboogie'] ?? null;
    }

    /**
     * @param array<string, mixed> $fragments
     *
     * @return ExtensionAbstract[]
     */
    private function collect_extensions(array $fragments): array
    {
        $extensions = [];

        foreach ($fragments as $fragment) {
            if (empty($fragment[SchemaOptions::AUTOCONFIG_EXTENSION])) {
                continue;
            }

            $class = $fragment[SchemaOptions::AUTOCONFIG_EXTENSION];
            $extensions[] = new $class($this);
        }

        return $extensions;
    }

    /**
     * Validate fragments against schema.
     *
     * @param array<string, mixed> $fragments
     *
     * @throws Throwable
     */
    private function validate_fragments(array $fragments): void
    {
        $data = Schema::read(__DIR__ . '/schema.json');
        $properties = &$data->properties;
        $set_property = function ($property, array $data) use (&$properties): void {
            $properties->$property = (object) $data;
        };

        foreach ($this->extensions as $extension) {
            $extension->alter_schema($set_property);
        }

        $schema = new Schema($data);

        foreach ($fragments as $pathname => $fragment) {
            $schema->validate(Schema::normalize_data($fragment), $pathname);
        }
    }

    /**
     * @param array<string, mixed> $fragment
     */
    private function resolve_config_weight(PackageInterface $package, array $fragment): int
    {
        if (isset($fragment[SchemaOptions::CONFIG_WEIGHT])) {
            return $fragment[SchemaOptions::CONFIG_WEIGHT];
        }

        if ($package instanceof RootPackage) {
            return Autoconfig::CONFIG_WEIGHT_APP;
        }

        return Autoconfig::CONFIG_WEIGHT_FRAMEWORK;
    }

    /**
     * Synthesize the autoconfig fragments into a single array.
     *
     * @return array<string, mixed>
     */
    private function synthesize(): array
    {
        static $mapping = [

            SchemaOptions::CONFIG_CONSTRUCTOR => Autoconfig::CONFIG_CONSTRUCTOR,
            SchemaOptions::CONFIG_PATH => Autoconfig::CONFIG_PATH,
            SchemaOptions::LOCALE_PATH => Autoconfig::LOCALE_PATH,
            SchemaOptions::AUTOCONFIG_FILTERS => Autoconfig::AUTOCONFIG_FILTERS,
            SchemaOptions::APP_PATHS => Autoconfig::APP_PATHS,

        ];

        $config = [

            Autoconfig::CONFIG_CONSTRUCTOR => [],
            Autoconfig::CONFIG_PATH => [],
            Autoconfig::LOCALE_PATH => [],
            Autoconfig::AUTOCONFIG_FILTERS => [],
            Autoconfig::APP_PATHS => []

        ];

        foreach ($this->fragments as $path => $fragment) {
            foreach ($fragment as $key => $value) {
                switch ($key) {
                    case SchemaOptions::CONFIG_CONSTRUCTOR:
                    case SchemaOptions::AUTOCONFIG_FILTERS:
                    case SchemaOptions::APP_PATHS:
                        $key = $mapping[$key];
                        $config[$key] = array_merge($config[$key], (array) $value);

                        break;

                    case SchemaOptions::CONFIG_PATH:
                        foreach ((array) $value as $v) {
                            $config[Autoconfig::CONFIG_PATH][] = [

                                $this->find_shortest_path_code("$path/$v"),
                                $this->weights[$path]

                            ];
                        }

                        break;

                    case SchemaOptions::LOCALE_PATH:
                        $key = $mapping[$key];

                        foreach ((array) $value as $v) {
                            $config[$key][] = $this->find_shortest_path_code("$path/$v");
                        }

                        break;
                }
            }
        }

        foreach ($this->extensions as $extension) {
            $extension->synthesize($config);
        }

        return $config;
    }

    /**
     * Render the synthesized autoconfig into a string.
     *
     * @param array<string, mixed> $config Synthesized config.
     */
    private function render(array $config = []): string
    {
        if (!$config) {
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

        foreach ($this->extensions as $extension) {
            $rendered_entries[] = $extension->render();
        }

        $extension_render = implode(array_map(function ($rendered_entry) {
            return "\n$rendered_entry\n";
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
     * @param array<string, mixed> $config
     */
    private function render_app_paths(array $config): string
    {
        return $this->render_array_entry(
            Autoconfig::APP_PATHS,
            $config[Autoconfig::APP_PATHS],
            function ($item): string {
                return (string) $item;
            }
        );
    }

    /**
     * Render the {@link Autoconfig::LOCALE_PATH} part of the autoconfig.
     *
     * @param array<string, mixed> $config
     */
    private function render_locale_paths(array $config): string
    {
        return $this->render_array_entry(
            Autoconfig::LOCALE_PATH,
            $config[Autoconfig::LOCALE_PATH],
            function ($item): string {
                return (string) $item;
            }
        );
    }

    /**
     * Render the {@link Autoconfig::CONFIG_CONSTRUCTOR} part of the autoconfig.
     *
     * @param array<string, mixed> $config
     */
    private function render_config_constructor(array $config): string
    {
        $synthesized = $config[Autoconfig::CONFIG_CONSTRUCTOR];
        ksort($synthesized);

        return $this->render_array_entry(
            Autoconfig::CONFIG_CONSTRUCTOR,
            $synthesized,
            function ($constructor, $name): string {
                [ $callback, $from ] = explode('#', $constructor) + [ 1 => null ];

                return "'$name' => [ '$callback'" . ($from ? ", '$from'" : '') . " ]";
            }
        );
    }

    /**
     * Render the {@link Autoconfig::CONFIG_PATH} part of the autoconfig.
     *
     * @param array<string, mixed> $config
     */
    private function render_config_path(array $config): string
    {
        return $this->render_array_entry(
            Autoconfig::CONFIG_PATH,
            $config[Autoconfig::CONFIG_PATH],
            function ($item): string {
                [ $path_code, $weight ] = $item;

                return "$path_code => $weight";
            }
        );
    }

    /**
     * Render the {@link Autoconfig::AUTOCONFIG_FILTERS} part of the autoconfig.
     *
     * @param array<string, mixed> $config
     */
    private function render_filters(array $config): string
    {
        return $this->render_array_entry(
            Autoconfig::AUTOCONFIG_FILTERS,
            $config[Autoconfig::AUTOCONFIG_FILTERS],
            function ($callable): string {
                return "'$callable'";
            }
        );
    }

    /**
     * Write the autoconfig file.
     *
     * @throws Throwable
     */
    private function write(): void
    {
        try {
            file_put_contents($this->destination, $this->render());

            echo "Created Autoconfig in $this->destination\n";
        } catch (Throwable $e) {
            echo $e;

            throw $e;
        }
    }
}
