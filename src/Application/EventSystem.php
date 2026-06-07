<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Application;

use SymPress\EventDispatcher\Dispatcher\EventDispatcher;
use SymPress\EventDispatcher\Dispatcher\HookEventDispatcher;
use SymPress\EventDispatcher\Dispatcher\ListenerDefinitionResolver;
use SymPress\EventDispatcher\Dispatcher\ListenerIdentifierFactory;
use SymPress\EventDispatcher\Dispatcher\ListenerMetadataFactory;
use SymPress\EventDispatcher\Dispatcher\ListenerProvider;

final class EventSystem
{
    public const string REGISTER_HOOK = 'event_dispatcher_register';
    public const string REGISTERED_HOOK = 'event_dispatcher_registered';
    public const string READY_HOOK = 'event_dispatcher_ready';

    private static ?self $instance = null;

    private bool $initialized = false;
    private readonly HookEventDispatcher $dispatcher;

    public function __construct(?HookEventDispatcher $dispatcher = null)
    {
        $this->dispatcher = $dispatcher ?? self::buildDispatcher();
    }

    public static function getInstance(): self
    {
        return self::bootstrap();
    }

    public static function bootstrap(?HookEventDispatcher $dispatcher = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($dispatcher);
        }

        return self::$instance;
    }

    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->registerHooks();
        $this->initialized = true;
    }

    public function getDispatcher(): HookEventDispatcher
    {
        return $this->dispatcher;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function dispatchRegistrationHook(): void
    {
        do_action(self::REGISTER_HOOK, $this->dispatcher);
    }

    public function dispatchRegisteredHook(): void
    {
        do_action(self::REGISTERED_HOOK, $this->dispatcher);
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function registerHooks(): void
    {
        add_action('init', [$this, 'dispatchRegistrationHook'], 5);
        add_action('init', [$this, 'dispatchRegisteredHook'], 15);
    }

    private static function buildDispatcher(): HookEventDispatcher
    {
        $listenerIdentifierFactory = new ListenerIdentifierFactory();
        $listenerProvider = new ListenerProvider(
            new ListenerMetadataFactory($listenerIdentifierFactory),
            $listenerIdentifierFactory,
        );
        $listenerDefinitionResolver = new ListenerDefinitionResolver();

        return new HookEventDispatcher(
            new EventDispatcher($listenerProvider, $listenerDefinitionResolver),
            $listenerProvider,
            $listenerDefinitionResolver,
        );
    }
}
