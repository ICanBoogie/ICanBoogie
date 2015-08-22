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

/**
 * Accessor for the `$app` property.
 *
 * @property-read Core $app
 */
trait AppAccessor
{
	/**
	 * @return Core
	 */
	protected function get_app()
	{
		return app();
	}
}
