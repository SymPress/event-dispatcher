<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Dispatcher;

use Psr\EventDispatcher\StoppableEventInterface;
use SymPress\EventDispatcher\Contract\EventSubscriberInterface;
use SymPress\EventDispatcher\Contract\HookEventInterface;
use SymPress\EventDispatcher\Contract\ListenerRegistryInterface;
use SymPress\EventDispatcher\Event\HookType;
use SymPress\EventDispatcher\Exception\InvalidHookEvent;
use SymPress\EventDispatcher\Value\ListenerDefinition;

final class HookEventDispatcher implements ListenerRegistryInterface
{
    /** @var array<class-string<HookEventInterface>, \Closure> */
    private array $registeredHookEvents = [];

    /** @var array<string, true> */
    private array $compiledListeners = [];
    private bool $debug = false;

    public function configureDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /** @param callable(): object $resolveService */
    public function addCompiledListener(string $serviceId, string $eventName, string $method, int $priority, callable $resolveService): void
    {
        $key = $serviceId . ':' . $eventName . ':' . $method;
        if (isset($this->compiledListeners[$key]) || !$this->registerHookEvent($eventName)) {
            return;
        }
        $this->dispatcher->addListener($eventName, function (object $event) use ($resolveService, $eventName, $method): mixed {
            $service = $resolveService();
            // Suppress only the same resolved listener; distinct explicit tags remain active.
            if ($this->dispatcher->hasRegisteredListener($service, $eventName, $method)) {
                return null;
            }
            return $service->{$method}($event);
        }, $priority);
        $this->compiledListeners[$key] = true;
    }

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly ListenerProvider $listenerProvider,
        private readonly ListenerDefinitionResolver $listenerDefinitionResolver,
    ) {
    }

    #[\Override]
    public function register(object $service): void
    {
        $resolved = $this->listenerDefinitionResolver->resolve($service);
        $definitions = array_values(array_filter(
            $resolved,
            fn (ListenerDefinition $definition): bool => $this->registerHookEvent($definition->eventName),
        ));

        if ($definitions === [] && $resolved !== []) {
            return;
        }

        $this->dispatcher->registerDefinitions($service, $definitions);
    }

    #[\Override]
    public function unregister(object $service): void
    {
        $this->dispatcher->unregister($service);
        $this->removeUnusedHooks();
    }

    #[\Override]
    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        if (!$this->registerHookEvent($eventName)) {
            return;
        }
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    #[\Override]
    public function removeListener(string $eventName, callable $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
        $this->removeUnusedHooks();
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

    private function registerHookEvent(string $eventName): bool
    {
        if (!is_a($eventName, HookEventInterface::class, true)) {
            return true;
        }

        try {
            $this->assertValidHookEvent($eventName);
        } catch (InvalidHookEvent $exception) {
            return $this->invalidHook($exception);
        }
        $hook = $eventName::hookName();
        $fired = $eventName::hookType() === HookType::Action
            ? function_exists('did_action') && did_action($hook) > 0
            : function_exists('did_filter') && did_filter($hook) > 0;
        $running = function_exists('doing_action') && doing_action($hook);
        $bootstrapHooks = [
            'muplugins_loaded', 'plugins_loaded', 'setup_theme', 'after_setup_theme', 'init', 'wp_loaded',
        ];
        if (($fired || $running) && in_array($hook, $bootstrapHooks, true)) {
            $message = 'Cannot register a listener after its bootstrap hook has started.';
            return $this->invalidHook(new InvalidHookEvent($message));
        }
        if ($fired && function_exists('_doing_it_wrong')) {
            _doing_it_wrong(
                __METHOD__,
                'This listener missed an earlier hook invocation; only future invocations will be observed.',
                '1.0',
            );
        }
        if (isset($this->registeredHookEvents[$eventName])) {
            return true;
        }
        $callback = $this->createHookCallback($eventName);

        if ($eventName::hookType() === HookType::Action) {
            add_action(
                $eventName::hookName(),
                $callback,
                $eventName::hookPriority(),
                $eventName::acceptedArgs(),
            );

            $this->registeredHookEvents[$eventName] = $callback;

            return true;
        }

        add_filter(
            $eventName::hookName(),
            $callback,
            $eventName::hookPriority(),
            $eventName::acceptedArgs(),
        );

        $this->registeredHookEvents[$eventName] = $callback;
        return true;
    }

    private function invalidHook(InvalidHookEvent $exception): bool
    {
        if ($this->debug) {
            throw $exception;
        }
        if (function_exists('_doing_it_wrong')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant developer diagnostics are handled by WordPress.
            _doing_it_wrong(__METHOD__, $exception->getMessage(), '1.0');
        }
        return false;
    }

    private function removeUnusedHooks(): void
    {
        foreach ($this->registeredHookEvents as $class => $callback) {
            $used = false;
            foreach (array_keys($this->listenerProvider->getListeners()) as $type) {
                if (is_string($type) && is_a($class, $type, true)) {
                    $used = true;
                    break;
                }
            }
            if ($used) {
                continue;
            }
            if ($class::hookType() === HookType::Action) {
                remove_action($class::hookName(), $callback, $class::hookPriority());
                unset($this->registeredHookEvents[$class]);
                continue;
            }
            remove_filter($class::hookName(), $callback, $class::hookPriority());
            unset($this->registeredHookEvents[$class]);
        }
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
