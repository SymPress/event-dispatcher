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
            $dispatcher->addCompiledListener(
                $entry['service'],
                $entry['event'],
                $entry['method'],
                $entry['priority'],
                function () use ($entry): object {
                    $service = $this->locator->get($entry['service']);
                    if (!is_object($service)) {
                        throw new \UnexpectedValueException('Compiled listeners must resolve to a service object.');
                    }
                    return $service;
                },
            );
        }
        $this->registered = true;
    }
}
