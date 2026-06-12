<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Dispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;
use SymPress\EventDispatcher\Value\ListenerMetadata;

final class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, array<int, array<string, ListenerMetadata>>> */
    private array $listeners = [];

    /** @var array<string, list<ListenerMetadata>> */
    private array $listenersByType = [];

    /** @var array<class-string, list<ListenerMetadata>> */
    private array $resolvedListeners = [];

    private int $nextSequence = 0;

    private readonly ListenerMetadataFactory $listenerMetadataFactory;
    private readonly ListenerIdentifierFactory $listenerIdentifierFactory;

    public function __construct(
        ?ListenerMetadataFactory $listenerMetadataFactory = null,
        ?ListenerIdentifierFactory $listenerIdentifierFactory = null,
    ) {

        $this->listenerIdentifierFactory = $listenerIdentifierFactory ?? new ListenerIdentifierFactory();
        $this->listenerMetadataFactory = $listenerMetadataFactory
            ?? new ListenerMetadataFactory($this->listenerIdentifierFactory);
    }

    public function add(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->remove($eventName, $listener);

        $metadata = $this->listenerMetadataFactory->create(
            $eventName,
            $listener,
            $priority,
            $this->nextSequence++,
        );
        $this->listeners[$eventName][$priority][$metadata->identifier] = $metadata;

        unset($this->listenersByType[$eventName]);
        $this->resolvedListeners = [];
    }

    public function remove(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        $identifier = $this->listenerIdentifierFactory->create($listener);

        foreach (array_keys($this->listeners[$eventName]) as $priority) {
            unset($this->listeners[$eventName][$priority][$identifier]);

            if ($this->listeners[$eventName][$priority] !== []) {
                continue;
            }

            unset($this->listeners[$eventName][$priority]);
        }

        if ($this->listeners[$eventName] === []) {
            unset($this->listeners[$eventName]);
        }

        unset($this->listenersByType[$eventName]);
        $this->resolvedListeners = [];
    }

    /** @return list<ListenerMetadata> */
    public function listenerMetadataForType(string $eventName): array
    {
        if (!isset($this->listenersByType[$eventName])) {
            $this->listenersByType[$eventName] = $this->sortTypeListeners($eventName);
        }

        return $this->listenersByType[$eventName];
    }

    /** @return list<ListenerMetadata> */
    public function listenerMetadataForEvent(object $event): array
    {
        $eventClass = $event::class;

        if (!isset($this->resolvedListeners[$eventClass])) {
            $this->resolvedListeners[$eventClass] = $this->resolveListenersForEvent($event);
        }

        return $this->resolvedListeners[$eventClass];
    }

    /** @return array<string, list<\Closure>>|list<\Closure> */
    public function getListeners(?string $eventName = null): array
    {
        if ($eventName !== null) {
            return $this->closuresForType($eventName);
        }

        $listeners = [];

        foreach (array_keys($this->listeners) as $registeredEventName) {
            $listeners[$registeredEventName] = $this->closuresForType($registeredEventName);
        }

        return $listeners;
    }

    /** @return iterable<\Closure(object): mixed> */
    #[\Override]
    public function getListenersForEvent(object $event): iterable
    {
        return array_map(
            static fn (ListenerMetadata $metadata): \Closure => $metadata->listener,
            $this->listenerMetadataForEvent($event),
        );
    }

    public function hasListeners(?string $eventName = null): bool
    {
        if ($eventName !== null) {
            return $this->listenerMetadataForType($eventName) !== [];
        }

        return $this->listeners !== [];
    }

    /** @return list<ListenerMetadata> */
    private function sortTypeListeners(string $eventName): array
    {
        $listenersByPriority = $this->listeners[$eventName] ?? [];

        if ($listenersByPriority === []) {
            return [];
        }

        krsort($listenersByPriority);

        $sortedListeners = [];

        foreach ($listenersByPriority as $listeners) {
            foreach ($listeners as $listener) {
                $sortedListeners[] = $listener;
            }
        }

        return $sortedListeners;
    }

    /** @return list<\Closure> */
    private function closuresForType(string $eventName): array
    {
        return array_map(
            static fn (ListenerMetadata $metadata): \Closure => $metadata->listener,
            $this->listenerMetadataForType($eventName),
        );
    }

    /** @return list<ListenerMetadata> */
    private function resolveListenersForEvent(object $event): array
    {
        $listeners = [];

        foreach ($this->eventTypes($event) as $eventType) {
            foreach ($this->listenerMetadataForType($eventType) as $listenerMetadata) {
                $listeners[] = $listenerMetadata;
            }
        }

        usort(
            $listeners,
            static fn (ListenerMetadata $left, ListenerMetadata $right): int => $right->priority <=> $left->priority
                ?: $left->sequence <=> $right->sequence,
        );

        return $listeners;
    }

    /** @return list<string> */
    private function eventTypes(object $event): array
    {
        $types = [$event::class];

        foreach (class_parents($event) as $parentClass) {
            $types[] = $parentClass;
        }

        foreach (class_implements($event) as $interface) {
            $types[] = $interface;
        }

        return array_values(array_unique($types));
    }
}
