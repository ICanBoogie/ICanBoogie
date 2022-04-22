<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use ICanBoogie\Application;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;

use function array_key_exists;
use function microtime;

final class PingController implements Responder
{
    private const PARAM_TIMER = 'timer';

    private static function format_time(float $finish): string
    {
        return number_format(($finish - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 3, '.', '') . ' ms';
    }

    public function __construct(
        private readonly Application $app
    ) {
    }

    public function respond(Request $request): Response
    {
        $session = $this->app->session;

        // @codeCoverageIgnoreStart
        if ($session->is_referenced) {
            $session->start_or_reuse();
        }
        // @codeCoverageIgnoreEnd

        $rc = 'pong';

        if (array_key_exists(self::PARAM_TIMER, $request->query_params)) {
            $boot_time = self::format_time($_SERVER['ICANBOOGIE_READY_TIME_FLOAT']);
            $run_time = self::format_time(microtime(true));

            $rc .= ", in $run_time (ready in $boot_time)";
        }

        $response = new Response($rc);
        $response->headers->content_type = 'text/plain';

        return $response;
    }
}
