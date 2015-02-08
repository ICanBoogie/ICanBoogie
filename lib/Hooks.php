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

use ICanBoogie\Routing\Routes;

class Hooks
{
    /*
     * Prototypes
     */

    /**
     * Returns the route collection.
     *
     * @param Core $app
     *
     * @return Routes
     */
    static public function lazy_get_routes(Core $app)
    {
        return new Routes($app->configs['routes']);
    }
}
