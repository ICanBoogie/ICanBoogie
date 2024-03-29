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

use RuntimeException;

/**
 * Patchable helpers of the ICanBoogie package.
 *
 * @method static generate_token(int $length = 8, string $possible = TOKEN_NARROW)
 */
final class Helpers
{
    /**
     * @var array<string, callable>
     */
    private static array $jumpTable = [

        'generate_token' => [ __CLASS__, 'default_generate_token' ]

    ];

    /**
     * Calls the callback of a patchable function.
     *
     * @param string $name Name of the function.
     * @param mixed[] $arguments Arguments.
     *
     * @return mixed
     *
     * @uses default_generate_token()
     */
    public static function __callstatic(string $name, array $arguments)
    {
        $method = self::$jumpTable[$name];

        return $method(...$arguments);
    }

    /**
     * Patches a patchable function.
     *
     * @param string $name Name of the function.
     * @param callable $callback Callback.
     *
     * @throws RuntimeException is attempt to patch an undefined function.
     *
     * @codeCoverageIgnore
     */
    public static function patch(string $name, callable $callback): void
    {
        if (empty(self::$jumpTable[$name])) {
            throw new RuntimeException("Undefined patchable: $name.");
        }

        self::$jumpTable[$name] = $callback;
    }

    /*
     * Default implementations
     */

    private static function default_generate_token(int $length = 8, string $possible = TOKEN_NARROW): string
    {
        $token = '';
        $y = strlen($possible) - 1;

        while ($length--) {
            $i = mt_rand(0, $y);
            $token .= $possible[$i];
        }

        return $token;
    }
}
