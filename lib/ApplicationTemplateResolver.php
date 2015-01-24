<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\Render\TemplateResolverInterface;

/**
 * Decorates a template resolver and adds support for the application paths.
 *
 * @package ICanBoogie
 */
class ApplicationTemplateResolver implements TemplateResolverInterface
{
	/**
	 * Original template resolver.
	 *
	 * @var TemplateResolverInterface
	 */
	private $component;

	/**
	 * Application paths.
	 *
	 * @var array
	 */
	private $paths;

	/**
	 * @param TemplateResolverInterface $template_resolver
	 * @param array $paths Application paths.
	 */
	public function __construct(TemplateResolverInterface $template_resolver, array $paths)
	{
		$this->component = $template_resolver;
		$this->paths = array_reverse($paths);

		foreach ($paths as $path)
		{
			$template_resolver->add_path($path . 'templates');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function resolve($name, array $extensions, &$tries = [ ])
	{
		if (strpos($name, '//') === 0)
		{
			$name = substr($name, 2);
			$template = $this->resolve_from_app($name, $extensions, $tries);

			if ($template)
			{
				return $template;
			}
		}

		return $this->component->resolve($name, $extensions, $tries);
	}

	/**
	 * Resolves a name from the application paths.
	 *
	 * @param $name
	 * @param $extensions
	 * @param $tries
	 *
	 * @return null|string
	 */
	protected function resolve_from_app($name, array $extensions, &$tries)
	{
		if (pathinfo($name, PATHINFO_EXTENSION))
		{
			foreach ($this->paths as $path)
			{
				$try = $path . $name;
				$tries[] = $try;

				if (file_exists($try))
				{
					return $try;
				}
			}

			return null;
		}

		foreach ($this->paths as $path)
		{
			foreach ($extensions as $extension)
			{
				$try = $path . $name . $extension;
				$tries[] = $try;

				if (file_exists($try))
				{
					return $try;
				}
			}
		}

		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function add_path($path, $weight = 0)
	{
		return $this->component->add_path($path, $weight);
	}

	/**
	 * @inheritdoc
	 */
	public function get_paths()
	{
		return $this->component->get_paths();
	}
}
