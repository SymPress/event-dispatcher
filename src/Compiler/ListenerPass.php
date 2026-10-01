<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Compiler;

use SymPress\EventDispatcher\Application\CompiledListeners;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $services = [];
        $listeners = [];
        foreach ($container->findTaggedServiceIds('sympress.event_listener') as $id => $tags) {
            $services[$id] = new Reference($id);
            foreach ($tags as $tag) {
                if (
                    !is_array($tag)
                    || !is_string($tag['event'] ?? null)
                    || !is_string($tag['method'] ?? null)
                    || !is_int($tag['priority'] ?? null)
                ) {
                    throw new \InvalidArgumentException(
                        'Compiled listener tags require event, method and integer priority.',
                    );
                }
                $key = $id . ':' . $tag['event'] . ':' . $tag['method'];
                $listeners[$key] = [
                    'service'  => $id,
                    'event'    => $tag['event'],
                    'method'   => $tag['method'],
                    'priority' => $tag['priority'],
                ];
            }
        }
        $container->register(CompiledListeners::class, CompiledListeners::class)->setArguments([new ServiceLocatorArgument($services), array_values($listeners)]);
    }
}
