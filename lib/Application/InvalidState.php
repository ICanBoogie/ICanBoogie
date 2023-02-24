<?php

namespace ICanBoogie\Application;

use LogicException;

final class InvalidState extends LogicException
{
    public static function not_instantiated(): self
    {
        return new self("The application is not instantiated yet.");
    }

    public static function already_instantiated(): self
    {
        return new self("The application is already instantiated.");
    }

    public static function not_booted(): self
    {
        return new self("The application has not booted yet.");
    }

    public static function already_booted(): self
    {
        return new self("The application has already booted.");
    }

    public static function already_running(): self
    {
        return new self("The application is already running.");
    }
}
