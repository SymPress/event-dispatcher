<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Event;

use SymPress\EventDispatcher\Contract\HookEventInterface;

abstract readonly class AbstractHookEvent extends AbstractEvent implements HookEventInterface
{
    /** @param list<mixed> $arguments */
    protected function __construct(private array $arguments)
    {
    }

    #[\Override]
    public static function hookPriority(): int
    {
        return 10;
    }

    /** @return list<mixed> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    #[\Override]
    public function toHookResult(): mixed
    {
        return null;
    }
}
