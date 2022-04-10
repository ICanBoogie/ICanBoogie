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
 * Extends the PSR interface with the following methods:
 *
 * - `get_messages()`: Return the messages of a specified level.
 * - `fetch_messages()`: Return and clear the messages of a specified level.
 */
interface LoggerInterface extends \Psr\Log\LoggerInterface
{
    /**
     * Return the messages of a specified level
     *
     * @return string[]
     */
    public function get_messages(string $level): array;

    /**
     * Return and clear the messages of a specified level
     *
     * @return string[]
     */
    public function fetch_messages(string $level): array;
}
