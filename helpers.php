<?php

namespace ICanBoogie;

use ICanBoogie\Autoconfig\Autoconfig;

use function implode;

use const DIRECTORY_SEPARATOR;

/*
 * Application
 */

/**
 * Instantiate and boot the application.
 *
 * @param Autoconfig|null $autoconfig
 *     If `null`, the config is obtained with {@see Autoconfig::get()}.
 */
function boot(Autoconfig $autoconfig = null): Application
{
    $autoconfig ??= Autoconfig::get();
    $app = Application::new($autoconfig);
    $app->boot();

    return $app;
}

/**
 * Returns the {@link Application} instance.
 */
function app(): Application
{
    static $app;

    return $app ??= Application::get();
}

/*
 * Utils
 */

/**
 * Creates an excerpt of an HTML string.
 *
 * The following tags are preserved: A, P, CODE, DEL, EM, INS and STRONG.
 *
 * @param string $str HTML string.
 * @param int $limit The maximum number of words.
 *
 * @return string
 */
function excerpt($str, $limit = 55)
{
    static $allowed_tags = [

        'a',
        'p',
        'code',
        'del',
        'em',
        'ins',
        'strong'

    ];

    $str = strip_tags(trim($str), '<' . implode('><', $allowed_tags) . '>');
    $str = preg_replace('#(<p>|<p\s+[^\>]+>)\s*</p>#', '', $str);

    $parts = preg_split('#<([^\s>]+)([^>]*)>#m', $str, 0, PREG_SPLIT_DELIM_CAPTURE);

    # i+0: text
    # i+1: markup ('/' prefix for closing markups)
    # i+2: markup attributes

    $rc = '';
    $opened = [];

    foreach ($parts as $i => $part) {
        if ($i % 3 == 0) {
            $words = preg_split('#(\s+)#', $part, 0, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($words as $w => $word) {
                if ($w % 2 == 0) {
                    if (!$word) // TODO-20100908: strip punctuation
                    {
                        continue;
                    }

                    $rc .= $word;

                    if (!--$limit) {
                        break;
                    }
                } else {
                    $rc .= $word;
                }
            }

            if (!$limit) {
                break;
            }
        } else {
            if ($i % 3 == 1) {
                if ($part[0] == '/') {
                    $rc .= '<' . $part . '>';

                    array_shift($opened);
                } else {
                    array_unshift($opened, $part);

                    $rc .= '<' . $part . $parts[$i + 1] . '>';
                }
            }
        }
    }

    if (!$limit) {
        $rc .= ' <span class="excerpt-warp">[…]</span>';
    }

    if ($opened) {
        $rc .= '</' . implode('></', $opened) . '>';
    }

    return $rc;
}

/**
 * Removes the `DOCUMENT_ROOT` from the provided path.
 *
 * Note: Because this function is usually used to create URL path from server path, the directory
 * separator '\' is replaced by '/'.
 *
 * @param string $pathname
 *
 * @return string
 */
function strip_root($pathname)
{
    $root = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);
    $root = strtr($root, DIRECTORY_SEPARATOR == '/' ? '\\' : '/', DIRECTORY_SEPARATOR);
    $pathname = strtr($pathname, DIRECTORY_SEPARATOR == '/' ? '\\' : '/', DIRECTORY_SEPARATOR);

    if ($root && strpos($pathname, $root) === 0) {
        $pathname = substr($pathname, strlen($root));
    }

    if (DIRECTORY_SEPARATOR != '/') {
        $pathname = strtr($pathname, DIRECTORY_SEPARATOR, '/');
    }

    return $pathname;
}
