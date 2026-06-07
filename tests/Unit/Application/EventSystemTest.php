<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Tests\Unit\Application;

use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Dispatcher\HookEventDispatcher;
use SymPress\EventDispatcher\Tests\Support\HookState;
use SymPress\EventDispatcher\Tests\Support\HookSubscriber;
use PHPUnit\Framework\TestCase;

final class EventSystemTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        HookState::reset();
        EventSystem::reset();
    }

    public function test_it_dispatches_the_registration_hooks_during_init(): void
    {
        $events = [];
        $subscriber = new HookSubscriber();

        add_action(
            EventSystem::REGISTER_HOOK,
            static function (HookEventDispatcher $dispatcher) use (&$events, $subscriber): void {
                $events[] = 'register';
                $dispatcher->addSubscriber($subscriber);
            },
            10,
            1,
        );

        add_action(
            EventSystem::REGISTERED_HOOK,
            static function () use (&$events): void {
                $events[] = 'registered';
            },
            10,
            0,
        );

        $system = EventSystem::getInstance();
        $system->init();
        do_action('init');
        $result = apply_filters('upload_mimes', ['jpg' => 'image/jpeg'], 8);

        self::assertTrue($system->isInitialized());
        self::assertSame(['register', 'registered'], $events);
        self::assertSame('image/svg+xml', $result['svg'] ?? null);
        self::assertSame('image/webp', $result['webp'] ?? null);
    }
}
