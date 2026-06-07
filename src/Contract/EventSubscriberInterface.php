<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Contract;

interface EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0: string, 1?: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array;
}
