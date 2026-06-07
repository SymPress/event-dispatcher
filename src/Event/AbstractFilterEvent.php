<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Event;

abstract readonly class AbstractFilterEvent extends AbstractHookEvent
{
    public function __construct(private mixed $value, array $arguments)
    {
        parent::__construct($arguments);
    }

    #[\Override]
    final public static function hookType(): HookType
    {
        return HookType::Filter;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function toHookResult(): mixed
    {
        return $this->value;
    }
}
