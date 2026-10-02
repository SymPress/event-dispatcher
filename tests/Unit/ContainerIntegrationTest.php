<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\EventDispatcherBundle;
use SymPress\EventDispatcher\Hook\EventSystemBootstrap;
use SymPress\EventDispatcher\Tests\Support\AttributedHookSubscriber;
use SymPress\EventDispatcher\Tests\Support\HookState;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class ContainerIntegrationTest extends TestCase
{
    public function testAttributesCompileAndRegisterLazyServicesExactlyOnce(): void
    {
        HookState::reset();
        EventSystem::reset();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new EventDispatcherBundle())->build($container);
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/Resources/config')))->load('services.yaml');
        $container->register(AttributedHookSubscriber::class, AttributedHookSubscriber::class)->setAutoconfigured(true)->setPublic(true);
        $container->getDefinition(EventSystemBootstrap::class)->setPublic(true);
        $container->compile();
        $bootstrap = $container->get(EventSystemBootstrap::class);
        $bootstrap->initialize();
        $bootstrap->initialize();
        self::assertFalse($container->initialized(AttributedHookSubscriber::class));
        apply_filters('upload_mimes', [], 9);
        apply_filters('upload_mimes', [], 10);
        self::assertSame([9, 9, 10, 10], $container->get(AttributedHookSubscriber::class)->filterUsers);
        self::assertCount(1, HookState::$hooks['upload_mimes'][10]);
    }
}
