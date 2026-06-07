<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Contract;

interface ListenerRegistryInterface extends EventDispatcherInterface, \Psr\EventDispatcher\ListenerProviderInterface
{
    public function register(object $service): void;

    public function unregister(object $service): void;

    public function addListener(string $eventName, callable $listener, int $priority = 0): void;

    public function removeListener(string $eventName, callable $listener): void;

    public function addSubscriber(EventSubscriberInterface $subscriber): void;

    public function removeSubscriber(EventSubscriberInterface $subscriber): void;

    /**
     * @return array<string, list<\Closure>>|list<\Closure>
     */
    public function getListeners(?string $eventName = null): array;

    public function hasListeners(?string $eventName = null): bool;
}
