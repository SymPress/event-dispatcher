<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Dispatcher;

use Psr\EventDispatcher\StoppableEventInterface;
use SymPress\EventDispatcher\Contract\EventSubscriberInterface;
use SymPress\EventDispatcher\Contract\HookEventInterface;
use SymPress\EventDispatcher\Contract\ListenerRegistryInterface;
use SymPress\EventDispatcher\Event\HookType;
use SymPress\EventDispatcher\Exception\InvalidHookEvent;

final class HookEventDispatcher implements ListenerRegistryInterface
{
    /** @var array<class-string<HookEventInterface>, true> */
    private array $registeredHookEvents = [];

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly ListenerProvider $listenerProvider,
        private readonly ListenerDefinitionResolver $listenerDefinitionResolver,
    ) {
    }

    #[\Override]
    public function register(object $service): void
    {
        foreach ($this->listenerDefinitionResolver->resolve($service) as $definition) {
            $this->registerHookEvent($definition->eventName);
        }

        $this->dispatcher->register($service);
    }

    #[\Override]
    public function unregister(object $service): void
    {
        $this->dispatcher->unregister($service);
    }

    #[\Override]
    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
        $this->registerHookEvent($eventName);
    }

    #[\Override]
    public function removeListener(string $eventName, callable $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
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
        return $this->dispatcher->dispatch($event);
    }

    /** @return array<string, list<\Closure>>|list<\Closure> */
    #[\Override]
    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    #[\Override]
    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    /** @return iterable<\Closure(object): mixed> */
    #[\Override]
    public function getListenersForEvent(object $event): iterable
    {
        return $this->listenerProvider->getListenersForEvent($event);
    }

    /**
     * @param class-string<HookEventInterface> $eventClass
     * @param list<mixed> $arguments
     */
    public function dispatchHookEvent(string $eventClass, array $arguments): mixed
    {
        $event = $eventClass::fromHookArguments($arguments);

        foreach ($this->listenerProvider->listenerMetadataForEvent($event) as $listenerMetadata) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $result = ($listenerMetadata->listener)($event);

            if (!($result instanceof $eventClass)) {
                continue;
            }

            $event = $result;
        }

        return $event->toHookResult();
    }

    /** @return list<class-string<HookEventInterface>> */
    public function registeredHookEvents(): array
    {
        return array_keys($this->registeredHookEvents);
    }

    private function registerHookEvent(string $eventName): void
    {
        if (!is_a($eventName, HookEventInterface::class, true)) {
            return;
        }

        if (isset($this->registeredHookEvents[$eventName])) {
            return;
        }

        $this->assertValidHookEvent($eventName);
        $callback = $this->createHookCallback($eventName);

        if ($eventName::hookType() === HookType::Action) {
            add_action(
                $eventName::hookName(),
                $callback,
                $eventName::hookPriority(),
                $eventName::acceptedArgs(),
            );

            $this->registeredHookEvents[$eventName] = true;

            return;
        }

        add_filter(
            $eventName::hookName(),
            $callback,
            $eventName::hookPriority(),
            $eventName::acceptedArgs(),
        );

        $this->registeredHookEvents[$eventName] = true;
    }

    /** @param class-string<HookEventInterface> $eventClass */
    private function createHookCallback(string $eventClass): \Closure
    {
        return function (mixed ...$arguments) use ($eventClass): mixed {
            return $this->dispatchHookEvent($eventClass, array_values($arguments));
        };
    }

    /** @param class-string<HookEventInterface> $eventClass */
    private function assertValidHookEvent(string $eventClass): void
    {
        if ($eventClass::hookName() === '') {
            throw new InvalidHookEvent('Hook events must define a hook name.');
        }

        if ($eventClass::acceptedArgs() < 0) {
            throw new InvalidHookEvent(
                'Hook events must define a non-negative accepted args value.',
            );
        }

        if ($eventClass::hookType() !== HookType::Filter) {
            return;
        }

        if ($eventClass::acceptedArgs() >= 1) {
            return;
        }

        throw new InvalidHookEvent('Filter events must accept at least one argument.');
    }
}
