<?php

namespace ICanBoogie;

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
     */
    public function service_for_id(string $id, string $class): object;
}
