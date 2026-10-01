<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Tests\Unit\Dispatcher;

use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Tests\Support\AllowedMimeTypesEvent;
use SymPress\EventDispatcher\Tests\Support\AttributedHookSubscriber;
use SymPress\EventDispatcher\Tests\Support\HookState;
use SymPress\EventDispatcher\Tests\Support\HookSubscriber;
use PHPUnit\Framework\TestCase;

final class HookEventDispatcherTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        HookState::reset();
        EventSystem::reset();
    }

    public function testRemovalDetachesOnlyOwnedCallbackAndReaddDispatchesOnce(): void
    {
        $dispatcher = EventSystem::getInstance()->getDispatcher();
        $listener = static fn (AllowedMimeTypesEvent $event): AllowedMimeTypesEvent => $event->withAllowed('svg', 'image/svg+xml');
        $unrelated = static fn (array $mimes): array => $mimes;
        add_filter('upload_mimes', $unrelated);
        $dispatcher->addListener(AllowedMimeTypesEvent::class, $listener);
        self::assertCount(2, HookState::$hooks['upload_mimes'][10]);
        $dispatcher->removeListener(AllowedMimeTypesEvent::class, $listener);
        self::assertSame([], $dispatcher->registeredHookEvents());
        self::assertCount(1, HookState::$hooks['upload_mimes'][10]);
        $dispatcher->addListener(AllowedMimeTypesEvent::class, $listener);
        $dispatcher->addListener(AllowedMimeTypesEvent::class, $listener);
        self::assertCount(2, HookState::$hooks['upload_mimes'][10]);
        self::assertSame(['svg' => 'image/svg+xml'], apply_filters('upload_mimes', []));
    }

    public function testLateRepeatedActionsWarnAndFutureExecutionsContinue(): void
    {
        do_action('save_post', 1, false);
        $subscriber = new HookSubscriber();
        EventSystem::getInstance()->getDispatcher()->register($subscriber);
        self::assertCount(1, HookState::$warnings);
        do_action('save_post', 2, true);
        do_action('save_post', 3, false);
        self::assertSame(['2:1', '3:0'], $subscriber->actions);
    }

    public function testLateBootstrapListenerIsRejectedBeforeRegistration(): void
    {
        do_action('init');
        $dispatcher = EventSystem::getInstance()->getDispatcher();
        try {
            $dispatcher->addListener(\SymPress\EventDispatcher\Tests\Support\InitEvent::class, static fn (object $event): object => $event);
            self::fail('Late init listener accepted.');
        } catch (\SymPress\EventDispatcher\Exception\InvalidHookEvent) {
            self::assertFalse($dispatcher->hasListeners());
            self::assertSame([], $dispatcher->registeredHookEvents());
        }
    }

    public function testExistingNativeCallbackDoesNotAllowAnotherLateBootstrapListener(): void
    {
        $dispatcher = EventSystem::getInstance()->getDispatcher();
        $event = \SymPress\EventDispatcher\Tests\Support\InitEvent::class;
        $dispatcher->addListener($event, static fn (object $value): object => $value);
        do_action('init');
        try {
            $dispatcher->addListener($event, static fn (object $value): object => $value);
            self::fail('Existing native callback accepted a late listener.');
        } catch (\SymPress\EventDispatcher\Exception\InvalidHookEvent) {
            self::assertCount(1, $dispatcher->getListeners($event));
            self::assertSame([$event], $dispatcher->registeredHookEvents());
        }
    }

    public function test_it_registers_filter_events_only_once_and_returns_the_immutable_result(): void
    {
        $dispatcher = EventSystem::getInstance()->getDispatcher();
        $subscriber = new HookSubscriber();

        $dispatcher->addSubscriber($subscriber);
        $dispatcher->addListener(
            AllowedMimeTypesEvent::class,
            static fn (AllowedMimeTypesEvent $event): AllowedMimeTypesEvent => $event->withAllowed(
                'avif',
                'image/avif',
            ),
            50,
        );

        $result = apply_filters('upload_mimes', ['jpg' => 'image/jpeg'], 77);

        self::assertCount(1, HookState::$hooks['upload_mimes'][10]);
        self::assertSame(
            [
                'jpg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                'avif' => 'image/avif',
                'webp' => 'image/webp',
            ],
            $result,
        );
        self::assertSame([77, 77], $subscriber->filterUsers);
    }

    public function test_it_registers_attributed_hook_services(): void
    {
        $dispatcher = EventSystem::getInstance()->getDispatcher();
        $subscriber = new AttributedHookSubscriber();

        $dispatcher->register($subscriber);
        $result = apply_filters('upload_mimes', ['jpg' => 'image/jpeg'], 91);
        do_action('save_post', 42, true);

        self::assertCount(1, HookState::$hooks['upload_mimes'][10]);
        self::assertSame(
            [
                'jpg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
            ],
            $result,
        );
        self::assertSame([91, 91], $subscriber->filterUsers);
        self::assertSame(['42:1'], $subscriber->actions);
    }

    public function test_it_registers_action_subscribers_and_dispatches_typed_events(): void
    {
        $dispatcher = EventSystem::getInstance()->getDispatcher();
        $subscriber = new HookSubscriber();

        $dispatcher->addSubscriber($subscriber);
        do_action('save_post', 42, true);

        self::assertSame(['42:1'], $subscriber->actions);
    }
}
