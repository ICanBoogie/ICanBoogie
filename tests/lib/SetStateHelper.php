<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie;

use function file_put_contents;
use function uniqid;
use function var_export;

final class SetStateHelper
{
    private const SANDBOX = __DIR__ . '/sandbox';

    /**
     * @template T of object
     *
     * @param T $object
     *
     * @return T
     */
    public static function export_import(object $object): object
    {
        $code = '<?php return ' . var_export($object, true) . ';';
        $filename = uniqid();
        $pathname = self::SANDBOX . "/$filename.php";

        file_put_contents($pathname, $code);

        return require $pathname;
    }
}
