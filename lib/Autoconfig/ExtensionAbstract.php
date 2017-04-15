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
	/**
	 * @var AutoconfigGenerator
	 */
	protected $generator;

	/**
	 * @param AutoconfigGenerator $generator
	 */
	public function __construct(AutoconfigGenerator $generator)
	{
		$this->generator = $generator;
	}

	/**
	 * Alter the autoconfig schema used to validate fragments.
	 *
	 * @param callable $set_property A callable used to set a property,
	 * with the following signature: `void (string $property, array $data)`
	 */
	public function alter_schema(callable $set_property)
	{

	}

	/**
	 * @param array $autoconfig
	 *
	 * @return void
	 */
	public function synthesize(array &$autoconfig)
	{

	}

	/**
	 * @return string
	 */
	abstract public function render();
}
