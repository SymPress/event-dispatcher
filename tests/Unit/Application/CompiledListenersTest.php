<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use SymPress\EventDispatcher\Application\CompiledListeners;
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Compiler\ListenerPass;
use SymPress\EventDispatcher\Tests\Support\AttributedHookSubscriber;
use SymPress\EventDispatcher\Tests\Support\HookState;
use SymPress\EventDispatcher\Tests\Support\MixedRegistrationSubscriber;
use SymPress\EventDispatcher\Tests\Support\SavePostEvent;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CompiledListenersTest extends TestCase
{
    public function testDistinctExplicitListenerSurvivesManualAttributedRegistration(): void
    {
        foreach ([true, false] as $manualFirst) {
            HookState::reset();
            EventSystem::reset();
            $container = new ContainerBuilder();
            $service = $container->register('subscriber', MixedRegistrationSubscriber::class)->setPublic(true);
            $service->addTag('sympress.event_listener', [
                'event' => SavePostEvent::class, 'method' => 'onManualSave', 'priority' => 100,
            ]);
            $service->addTag('sympress.event_listener', [
                'event' => SavePostEvent::class, 'method' => 'onCompiledSave', 'priority' => 0,
            ]);
            $container->setAlias('subscriber.alias', 'subscriber')->setPublic(true);
            (new ListenerPass())->process($container);
            $container->getDefinition(CompiledListeners::class)->setPublic(true);
            $container->compile();
            $dispatcher = EventSystem::getInstance()->getDispatcher();
            $subscriber = $container->get('subscriber.alias');
            if ($manualFirst) {
                $dispatcher->register($subscriber);
            }
            $container->get(CompiledListeners::class)->register($dispatcher);
            if (!$manualFirst) {
                $dispatcher->register($subscriber);
            }
            do_action('save_post', 42, true);
            self::assertSame(['manual:42', 'compiled:42'], $subscriber->calls);
        }
    }

    public function testAliasedManualServiceAndDuplicateAutomaticTagsDispatchOnce(): void
    {
        foreach (['automatic', 'manual-first', 'automatic-first'] as $mode) {
            HookState::reset();
            EventSystem::reset();
            $container = new ContainerBuilder();
            $service = $container->register('subscriber', AttributedHookSubscriber::class)->setPublic(true);
            foreach ([1, 2] as $unused) {
                $service->addTag('sympress.event_listener', ['event' => SavePostEvent::class, 'method' => 'onSavePost', 'priority' => 0]);
            }
            $container->setAlias('subscriber.alias', 'subscriber')->setPublic(true);
            (new ListenerPass())->process($container);
            $container->getDefinition(CompiledListeners::class)->setPublic(true);
            $container->compile();
            $dispatcher = EventSystem::getInstance()->getDispatcher();
            $compiled = $container->get(CompiledListeners::class);
            self::assertInstanceOf(CompiledListeners::class, $compiled);
            self::assertFalse($container->initialized('subscriber'));
            if ($mode === 'manual-first') {
                $dispatcher->register($container->get('subscriber.alias'));
            }
            $compiled->register($dispatcher);
            $compiled->register($dispatcher);
            if ($mode === 'automatic') {
                self::assertFalse($container->initialized('subscriber'));
            } elseif ($mode === 'automatic-first') {
                $dispatcher->register($container->get('subscriber.alias'));
            }
            do_action('save_post', 42, true);
            $subscriber = $container->get('subscriber');
            self::assertInstanceOf(AttributedHookSubscriber::class, $subscriber);
            self::assertSame(['42:1'], $subscriber->actions);
        }
    }
}
