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
 * Exception thrown when a security error occurs.
 *
 * This is a base class for security exceptions, one should rather use the
 * {@link AuthenticationRequired} and {@link PermissionRequired} exceptions.
 */
class SecurityException extends \Exception
{

}
