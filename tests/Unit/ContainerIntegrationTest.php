<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\EventDispatcherBundle;
use SymPress\EventDispatcher\Dispatcher\HookEventDispatcher;
use SymPress\EventDispatcher\Exception\InvalidHookEvent;
use SymPress\EventDispatcher\Hook\EventSystemBootstrap;
use SymPress\EventDispatcher\Tests\Support\AttributedHookSubscriber;
use SymPress\EventDispatcher\Tests\Support\HookState;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class ContainerIntegrationTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testWordPressDebugDoesNotEnableStandaloneBootstrapExceptions(): void
    {
        define('WP_DEBUG', true);
        HookState::reset();
        EventSystem::reset();
        $dispatcher = EventSystem::getInstance()->getDispatcher();
        $dispatcher->addListener(InvalidBootstrapEvent::class, static fn (): null => null);
        self::assertCount(1, HookState::$warnings);
        self::assertFalse($dispatcher->hasListeners(InvalidBootstrapEvent::class));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('debugModes')]
    public function testBootstrapConfiguresSingletonBeforeDispatcherServiceIsFetched(bool $debug): void
    {
        HookState::reset();
        EventSystem::reset();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        (new EventDispatcherBundle())->build($container);
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/Resources/config')))->load('services.yaml');
        $container->getDefinition(EventSystemBootstrap::class)->setPublic(true);
        $container->compile();
        $container->get(EventSystemBootstrap::class)->initialize();
        self::assertFalse($container->initialized(HookEventDispatcher::class));
        $dispatcher = EventSystem::getInstance()->getDispatcher();
        if ($debug) {
            $this->expectException(InvalidHookEvent::class);
        }
        $dispatcher->addListener(InvalidBootstrapEvent::class, static fn (): null => null);
        if (!$debug) {
            self::assertCount(1, HookState::$warnings);
            self::assertFalse($dispatcher->hasListeners(InvalidBootstrapEvent::class));
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function debugModes(): iterable
    {
        yield 'production' => [false];
        yield 'debug' => [true];
    }

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

final class InvalidBootstrapEvent implements \SymPress\EventDispatcher\Contract\HookEventInterface
{
    public static function hookName(): string { return ''; }
    public static function hookType(): \SymPress\EventDispatcher\Event\HookType { return \SymPress\EventDispatcher\Event\HookType::Action; }
    public static function hookPriority(): int { return 10; }
    public static function acceptedArgs(): int { return 1; }
    public static function fromHookArguments(array $arguments): static { return new self(); }
    public function toHookResult(): mixed { return null; }
}
