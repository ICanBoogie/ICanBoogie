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
 * @property-read Application $app
 *
 * @deprecated Use dependency injection instead
 */
trait AppAccessor
{
    protected function get_app(): Application
    {
        return app();
    }
}
