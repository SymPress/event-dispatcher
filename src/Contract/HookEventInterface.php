<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Contract;

use SymPress\EventDispatcher\Event\HookType;

interface HookEventInterface
{
    public static function hookName(): string;

    public static function hookType(): HookType;

    public static function acceptedArgs(): int;

    public static function hookPriority(): int;

    /** @param list<mixed> $arguments */
    public static function fromHookArguments(array $arguments): static;

    public function toHookResult(): mixed;
}
