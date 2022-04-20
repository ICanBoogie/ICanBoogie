<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Application;

use ICanBoogie\Application;
use ICanBoogie\Event;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;

/**
 * Event class for the `ICanBoogie\Application::terminate` event
 *
 * The event is fired after the response to the initial request was sent and that the application
 * is ready to be terminated.
 *
 * @codeCoverageIgnore
 */
final class TerminateEvent extends Event
{
    /**
     * The event is constructed with the type {@link TYPE}.
     */
    public function __construct(
        Application $target,
        public readonly Request $request,
        public readonly Response $response
    ) {
        parent::__construct($target);
    }
}
