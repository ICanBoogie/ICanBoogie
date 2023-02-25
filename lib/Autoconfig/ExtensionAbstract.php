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

/**
 * A base class for Autoconfig extensions.
 */
abstract class ExtensionAbstract
{
    public function __construct(
        private readonly AutoconfigGenerator $generator
    ) {
    }

    /**
     * Alter the autoconfig schema used to validate fragments.
     *
     * @param (callable(string $property, array<string, mixed> $data): void) $set_property
     *     A callable used to set a property.
     */
    public function alter_schema(callable $set_property): void
    {
    }

    /**
     * @param array<Autoconfig::ARG_*, mixed> $autoconfig
     */
    public function synthesize(array &$autoconfig): void
    {
    }

    abstract public function render(): string;

    protected function render_entry(string $key, string $value): string
    {
        return $this->generator->render_entry($key, $value);
    }

    /**
     * @param array<string, mixed> $items
     */
    protected function render_array_entry(string $key, array $items, callable $renderer): string
    {
        return $this->generator->render_array_entry($key, $items, $renderer);
    }

    protected function find_shortest_path_code(string $to): string
    {
        return $this->generator->find_shortest_path_code($to);
    }
}
