<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher;

use SymPress\EventDispatcher\Attribute\AsEventListener;
use SymPress\EventDispatcher\Attribute\AsEventSubscriber;
use SymPress\EventDispatcher\Compiler\ListenerPass;
use SymPress\EventDispatcher\Dispatcher\ListenerDefinitionResolver;
use SymPress\Kernel\Bundle\AbstractBundle;

final class EventDispatcherBundle extends AbstractBundle
{
    public function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);
        $resolver = new ListenerDefinitionResolver();
        $configure = static function (\Symfony\Component\DependencyInjection\ChildDefinition $definition, object $attribute, \Reflector $reflector) use ($resolver): void {
            unset($attribute);
            $class = $reflector instanceof \ReflectionMethod ? $reflector->getDeclaringClass()->getName() : ($reflector instanceof \ReflectionClass ? $reflector->getName() : null);
            if ($class === null) {
                return;
            }
            foreach ($resolver->resolveClassAttributes($class) as $listener) {
                $definition->addTag('sympress.event_listener', [
                    'event'    => $listener->eventName,
                    'method'   => $listener->methodName,
                    'priority' => $listener->priority,
                ]);
            }
        };
        $container->registerAttributeForAutoconfiguration(AsEventListener::class, $configure);
        $container->registerAttributeForAutoconfiguration(AsEventSubscriber::class, $configure);
        $container->addCompilerPass(new ListenerPass());
    }
}
