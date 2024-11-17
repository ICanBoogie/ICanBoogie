<?php

namespace ICanBoogie\Responder;

use ICanBoogie\Application;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;

use function array_key_exists;
use function microtime;

final class PingResponder implements Responder
{
    private const PARAM_TIMER = 'timer';

    private static function format_time(float $finish): string
    {
        /** @var float $request_time */
        $request_time = $_SERVER['REQUEST_TIME_FLOAT'];

        return number_format(
            ($finish - $request_time) * 1000,
            3,
            '.',
            '',
        ) . ' ms';
    }

    public function __construct(
        private readonly Application $app,
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
            /** @var float $timestamp */
            $timestamp = $_SERVER['ICANBOOGIE_READY_TIME_FLOAT'];

            $boot_time = self::format_time($timestamp);
            $run_time = self::format_time(microtime(true));

            $rc .= ", in $run_time (ready in $boot_time)";
        }

        $response = new Response($rc);
        $response->headers->content_type = 'text/plain';

        return $response;
    }
}
