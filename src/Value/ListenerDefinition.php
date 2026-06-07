<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Value;

final readonly class ListenerDefinition
{
    public function __construct(
        public string $eventName,
        public string $methodName,
        public int $priority = 0,
    ) {
    }
}
