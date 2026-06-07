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
