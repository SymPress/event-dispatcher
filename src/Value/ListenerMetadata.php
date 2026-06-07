<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Value;

final readonly class ListenerMetadata
{
    public function __construct(
        public string $eventName,
        public \Closure $listener,
        public string $identifier,
        public int $priority,
        public int $sequence,
    ) {
    }
}
