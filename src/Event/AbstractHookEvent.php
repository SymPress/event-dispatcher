<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Event;

use SymPress\EventDispatcher\Contract\HookEventInterface;

abstract readonly class AbstractHookEvent extends AbstractEvent implements HookEventInterface
{
    /** @var list<mixed> */
    private array $arguments;

    /**
     * @param list<mixed> $arguments
     */
    protected function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    #[\Override]
    public static function hookPriority(): int
    {
        return 10;
    }

    /**
     * @return list<mixed>
     */
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
