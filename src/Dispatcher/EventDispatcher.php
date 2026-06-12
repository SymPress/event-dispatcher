<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Dispatcher;

use Psr\EventDispatcher\StoppableEventInterface;
use SymPress\EventDispatcher\Contract\EventSubscriberInterface;
use SymPress\EventDispatcher\Contract\ListenerRegistryInterface;
use SymPress\EventDispatcher\Exception\InvalidListenerConfiguration;
use SymPress\EventDispatcher\Value\ListenerDefinition;

final class EventDispatcher implements ListenerRegistryInterface
{
    /** @var array<string, list<ListenerDefinition>> */
    private array $serviceDefinitions = [];

    private readonly ListenerProvider $listenerProvider;
    private readonly ListenerDefinitionResolver $listenerDefinitionResolver;

    public function __construct(
        ?ListenerProvider $listenerProvider = null,
        ?ListenerDefinitionResolver $listenerDefinitionResolver = null,
    ) {

        $this->listenerProvider = $listenerProvider ?? new ListenerProvider();
        $this->listenerDefinitionResolver = $listenerDefinitionResolver
            ?? new ListenerDefinitionResolver();
    }

    #[\Override]
    public function register(object $service): void
    {
        $serviceId = spl_object_hash($service);

        if (isset($this->serviceDefinitions[$serviceId])) {
            return;
        }

        $definitions = $this->listenerDefinitionResolver->resolve($service);

        if ($definitions === [] && !$service instanceof EventSubscriberInterface) {
            throw new InvalidListenerConfiguration(
                'Registered services must declare listeners via attributes '
                . 'or EventSubscriberInterface.',
            );
        }

        foreach ($definitions as $definition) {
            $listener = [$service, $definition->methodName];

            if (!is_callable($listener)) {
                throw new InvalidListenerConfiguration('Resolved listener method is not callable.');
            }

            $this->addListener(
                $definition->eventName,
                $listener,
                $definition->priority,
            );
        }

        $this->serviceDefinitions[$serviceId] = $definitions;
    }

    #[\Override]
    public function unregister(object $service): void
    {
        $serviceId = spl_object_hash($service);
        $definitions = $this->serviceDefinitions[$serviceId] ?? [];

        foreach ($definitions as $definition) {
            $listener = [$service, $definition->methodName];

            if (!is_callable($listener)) {
                continue;
            }

            $this->removeListener(
                $definition->eventName,
                $listener,
            );
        }

        unset($this->serviceDefinitions[$serviceId]);
    }

    #[\Override]
    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listenerProvider->add($eventName, $listener, $priority);
    }

    #[\Override]
    public function removeListener(string $eventName, callable $listener): void
    {
        $this->listenerProvider->remove($eventName, $listener);
    }

    #[\Override]
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->register($subscriber);
    }

    #[\Override]
    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->unregister($subscriber);
    }

    #[\Override]
    public function dispatch(object $event): object
    {
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }

            /** @var \Closure(object): mixed $listener */
            $listener($event);
        }

        return $event;
    }

    /** @return array<string, list<\Closure>>|list<\Closure> */
    #[\Override]
    public function getListeners(?string $eventName = null): array
    {
        return $this->listenerProvider->getListeners($eventName);
    }

    #[\Override]
    public function hasListeners(?string $eventName = null): bool
    {
        return $this->listenerProvider->hasListeners($eventName);
    }

    /** @return iterable<\Closure(object): mixed> */
    #[\Override]
    public function getListenersForEvent(object $event): iterable
    {
        return $this->listenerProvider->getListenersForEvent($event);
    }
}
