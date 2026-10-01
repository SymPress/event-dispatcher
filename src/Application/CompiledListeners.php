<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Application;

use Psr\Container\ContainerInterface;
use SymPress\EventDispatcher\Dispatcher\HookEventDispatcher;

final class CompiledListeners
{
    private bool $registered = false;

    /** @param list<array{service: string, event: string, method: string, priority: int}> $listeners */
    public function __construct(private readonly ContainerInterface $locator, private readonly array $listeners)
    {
    }

    public function register(HookEventDispatcher $dispatcher): void
    {
        if ($this->registered) {
            return;
        }
        foreach ($this->listeners as $entry) {
            $dispatcher->addListener($entry['event'], function (object $event) use ($entry): mixed {
                $service = $this->locator->get($entry['service']);
                return $service->{$entry['method']}($event);
            }, $entry['priority']);
        }
        $this->registered = true;
    }
}
