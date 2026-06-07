<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Event;

abstract readonly class AbstractActionEvent extends AbstractHookEvent
{
    #[\Override]
    final public static function hookType(): HookType
    {
        return HookType::Action;
    }
}
