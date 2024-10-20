<?php

namespace ICanBoogie;

use AssertionError;

interface ServiceProvider
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function service_for_class(string $class): object;

    /**
     * @template T of object
     *
     * @param string $id
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws AssertionError if the object doesn't match `$class`. Careful! Assertions need to be activated for the
     * exception to be thrown.
     */
    public function service_for_id(string $id, string $class): object;
}
