<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Tests\Unit\Dispatcher;

use SymPress\EventDispatcher\Dispatcher\EventDispatcher;
use SymPress\EventDispatcher\Exception\InvalidListenerConfiguration;
use SymPress\EventDispatcher\Exception\InvalidSubscriberConfiguration;
use SymPress\EventDispatcher\Tests\Support\AttributedSubscriberService;
use SymPress\EventDispatcher\Tests\Support\ChildDemoEvent;
use SymPress\EventDispatcher\Tests\Support\CountingSubscriber;
use SymPress\EventDispatcher\Tests\Support\DefaultMethodAttributedListener;
use SymPress\EventDispatcher\Tests\Support\DemoEvent;
use SymPress\EventDispatcher\Tests\Support\DemoEventMarkerInterface;
use SymPress\EventDispatcher\Tests\Support\HybridSubscriber;
use SymPress\EventDispatcher\Tests\Support\InvalidAttributedListener;
use SymPress\EventDispatcher\Tests\Support\InvalidConfigurationSubscriber;
use SymPress\EventDispatcher\Tests\Support\InvokableAttributedListener;
use SymPress\EventDispatcher\Tests\Support\MutableStoppableEvent;
use SymPress\EventDispatcher\Tests\Support\StopPropagationSubscriber;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    public function test_it_dispatches_listeners_in_descending_priority_order_and_ignores_returns(): void
    {
        $dispatcher = new EventDispatcher();
        $calls = [];
        $event = new DemoEvent('original');

        $dispatcher->addListener(DemoEvent::class, static function (DemoEvent $event) use (&$calls): DemoEvent {
            $calls[] = 'low';

            return $event->withName('changed-low');
        }, -10);
        $dispatcher->addListener(
            DemoEventMarkerInterface::class,
            static function (DemoEventMarkerInterface $event) use (&$calls): void {
                $calls[] = $event instanceof DemoEvent ? 'high' : 'unknown';
            },
            10,
        );

        $dispatchedEvent = $dispatcher->dispatch($event);

        self::assertSame($event, $dispatchedEvent);
        self::assertSame('original', $event->name);
        self::assertSame(['high', 'low'], $calls);
    }

    public function test_it_resolves_parent_event_type_listeners_for_child_events(): void
    {
        $dispatcher = new EventDispatcher();
        $calls = [];

        $dispatcher->addListener(DemoEvent::class, static function (DemoEvent $event) use (&$calls): void {
            $calls[] = $event->name;
        }, 10);

        $dispatcher->dispatch(new ChildDemoEvent('child'));

        self::assertSame(['child'], $calls);
    }

    public function test_it_stops_propagation_when_the_event_requests_it(): void
    {
        $dispatcher = new EventDispatcher();
        $event = new MutableStoppableEvent();
        $subscriber = new StopPropagationSubscriber();

        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch($event);

        self::assertSame(['stop'], $subscriber->calls);
    }

    public function test_it_adds_and_removes_subscribers(): void
    {
        $dispatcher = new EventDispatcher();
        $subscriber = new CountingSubscriber();

        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch(new DemoEvent('first'));
        $dispatcher->removeSubscriber($subscriber);
        $dispatcher->dispatch(new DemoEvent('second'));

        self::assertSame(['high:first', 'low:first'], $subscriber->calls);
    }

    public function test_it_registers_and_unregisters_attributed_listener_services(): void
    {
        $dispatcher = new EventDispatcher();
        $invokableListener = new InvokableAttributedListener();
        $defaultMethodListener = new DefaultMethodAttributedListener();
        $subscriber = new AttributedSubscriberService();

        $dispatcher->register($invokableListener);
        $dispatcher->register($defaultMethodListener);
        $dispatcher->register($subscriber);
        $dispatcher->dispatch(new DemoEvent('first'));
        $dispatcher->unregister($subscriber);
        $dispatcher->dispatch(new DemoEvent('second'));

        self::assertSame(['invoke:first', 'invoke:second'], $invokableListener->calls);
        self::assertSame(['default:first', 'default:second'], $defaultMethodListener->calls);
        self::assertSame(['attribute-high:first', 'attribute-low:first'], $subscriber->calls);
    }

    public function test_it_merges_interface_and_attribute_subscriptions_for_subscribers(): void
    {
        $dispatcher = new EventDispatcher();
        $subscriber = new HybridSubscriber();

        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch(new DemoEvent('hybrid'));

        self::assertSame(['attribute:hybrid', 'interface:hybrid'], $subscriber->calls);
    }

    public function test_it_rejects_invalid_subscriber_configurations(): void
    {
        $dispatcher = new EventDispatcher();

        $this->expectException(InvalidSubscriberConfiguration::class);

        $dispatcher->addSubscriber(new InvalidConfigurationSubscriber());
    }

    public function test_it_rejects_invalid_attribute_listener_configurations(): void
    {
        $dispatcher = new EventDispatcher();

        $this->expectException(InvalidListenerConfiguration::class);

        $dispatcher->register(new InvalidAttributedListener());
    }

    public function test_it_rejects_non_psr14_listener_signatures(): void
    {
        $dispatcher = new EventDispatcher();

        $this->expectException(\InvalidArgumentException::class);

        $dispatcher->addListener(
            DemoEvent::class,
            static function (DemoEvent $event, string $extra): void {
            },
        );
    }
}
