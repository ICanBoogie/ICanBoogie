<?php

namespace ICanBoogie\Application;

use ICanBoogie\Application;
use ICanBoogie\Event;

/**
 * The event is emitted when caches must be cleared.
 *
 * @codeCoverageIgnore
 */
final class ClearCacheEvent extends Event
{
    public function __construct(
        public readonly Application $app
    ) {
        parent::__construct();
    }

    /**
     * @var string[]
     */
    public array $cleared = [];

    public function cleared(string $message): void
    {
        $this->cleared[] = $message;
    }
}
