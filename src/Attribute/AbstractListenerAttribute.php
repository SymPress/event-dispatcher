<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Attribute;

abstract readonly class AbstractListenerAttribute
{
    public function __construct(
        public ?string $event = null,
        public ?string $method = null,
        public int $priority = 0,
    ) {
    }
}
