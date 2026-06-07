<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Dispatcher;

use SymPress\EventDispatcher\Attribute\AbstractListenerAttribute;
use SymPress\EventDispatcher\Attribute\AsEventListener;
use SymPress\EventDispatcher\Attribute\AsEventSubscriber;
use SymPress\EventDispatcher\Contract\EventSubscriberInterface;
use SymPress\EventDispatcher\Exception\InvalidListenerConfiguration;
use SymPress\EventDispatcher\Exception\InvalidSubscriberConfiguration;
use SymPress\EventDispatcher\Value\ListenerDefinition;

final class ListenerDefinitionResolver
{
    /**
     * @return list<ListenerDefinition>
     */
    public function resolve(object $service): array
    {
        $definitions = [];

        if ($service instanceof EventSubscriberInterface) {
            foreach ($this->resolveSubscriberDefinitions($service) as $definition) {
                $definitions[$this->definitionKey($definition)] = $definition;
            }
        }

        foreach ($this->resolveAttributeDefinitions($service) as $definition) {
            $definitions[$this->definitionKey($definition)] = $definition;
        }

        return array_values($definitions);
    }

    /**
     * @return list<ListenerDefinition>
     */
    private function resolveSubscriberDefinitions(EventSubscriberInterface $subscriber): array
    {
        $definitions = [];

        foreach ($subscriber::getSubscribedEvents() as $eventName => $configuration) {
            if ($eventName === '') {
                throw new InvalidSubscriberConfiguration(
                    'Subscriber event names must be non-empty strings.',
                );
            }

            foreach ($this->normalizeConfiguration($eventName, $configuration) as $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @return list<ListenerDefinition>
     */
    private function normalizeConfiguration(string $eventName, mixed $configuration): array
    {
        if (is_string($configuration) && $configuration !== '') {
            return [new ListenerDefinition($eventName, $configuration)];
        }

        if (!is_array($configuration) || $configuration === []) {
            throw new InvalidSubscriberConfiguration('Subscriber configuration is invalid.');
        }

        /** @var list<mixed> $normalizedConfiguration */
        $normalizedConfiguration = array_values($configuration);

        if ($this->isSingleListenerConfiguration($normalizedConfiguration)) {
            return [
                new ListenerDefinition(
                    $eventName,
                    $this->methodName($normalizedConfiguration),
                    $this->priority($normalizedConfiguration),
                ),
            ];
        }

        $definitions = [];

        foreach ($normalizedConfiguration as $nestedConfiguration) {
            if (!is_array($nestedConfiguration) || $nestedConfiguration === []) {
                throw new InvalidSubscriberConfiguration('Subscriber configuration is invalid.');
            }

            /** @var list<mixed> $normalizedNestedConfiguration */
            $normalizedNestedConfiguration = array_values($nestedConfiguration);

            if (!$this->isSingleListenerConfiguration($normalizedNestedConfiguration)) {
                throw new InvalidSubscriberConfiguration('Subscriber configuration is invalid.');
            }

            $definitions[] = new ListenerDefinition(
                $eventName,
                $this->methodName($normalizedNestedConfiguration),
                $this->priority($normalizedNestedConfiguration),
            );
        }

        return $definitions;
    }

    /**
     * @param array<int, mixed> $configuration
     */
    private function isSingleListenerConfiguration(array $configuration): bool
    {
        return isset($configuration[0]) && is_string($configuration[0]);
    }

    /**
     * @param array<int, mixed> $configuration
     */
    private function methodName(array $configuration): string
    {
        $methodName = $configuration[0] ?? null;

        if (!is_string($methodName) || $methodName === '') {
            throw new InvalidSubscriberConfiguration('Subscriber method configuration is invalid.');
        }

        return $methodName;
    }

    /**
     * @param array<int, mixed> $configuration
     */
    private function priority(array $configuration): int
    {
        $priority = $configuration[1] ?? 0;

        if (!is_int($priority)) {
            throw new InvalidSubscriberConfiguration('Subscriber priority must be an integer.');
        }

        return $priority;
    }

    /**
     * @return list<ListenerDefinition>
     */
    private function resolveAttributeDefinitions(object $service): array
    {
        $reflectionClass = new \ReflectionClass($service);
        $definitions = $this->classAttributeDefinitions(
            $reflectionClass,
            AsEventSubscriber::class,
        );

        return array_merge(
            $definitions,
            $this->classAttributeDefinitions($reflectionClass, AsEventListener::class),
            $this->methodAttributeDefinitions($reflectionClass),
        );
    }

    /**
     * @return list<ListenerDefinition>
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function definitionsFromClassAttribute(
        \ReflectionClass $reflectionClass,
        AbstractListenerAttribute $attribute,
    ): array {

        $methodName = $this->classAttributeMethodName($reflectionClass, $attribute);
        $reflectionMethod = $reflectionClass->getMethod($methodName);
        $definitions = [];

        foreach ($this->eventNames($reflectionMethod, $attribute) as $eventName) {
            $definitions[] = new ListenerDefinition(
                $eventName,
                $methodName,
                $attribute->priority,
            );
        }

        return $definitions;
    }

    /**
     * @return list<ListenerDefinition>
     */
    private function definitionsFromMethodAttribute(
        \ReflectionMethod $reflectionMethod,
        AsEventListener $attribute,
    ): array {

        if ($attribute->method !== null && $attribute->method !== $reflectionMethod->getName()) {
            throw new InvalidListenerConfiguration(
                'Method-level AsEventListener attributes cannot redefine the target method.',
            );
        }

        $this->assertPublicMethod($reflectionMethod);

        $definitions = [];

        foreach ($this->eventNames($reflectionMethod, $attribute) as $eventName) {
            $definitions[] = new ListenerDefinition(
                $eventName,
                $reflectionMethod->getName(),
                $attribute->priority,
            );
        }

        return $definitions;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     * @return list<ListenerDefinition>
     */
    private function classAttributeDefinitions(
        \ReflectionClass $reflectionClass,
        string $attributeClass,
    ): array {

        $definitions = [];

        foreach ($this->classAttributes($reflectionClass, $attributeClass) as $attribute) {
            foreach ($this->definitionsFromClassAttribute($reflectionClass, $attribute) as $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     * @return list<ListenerDefinition>
     */
    private function methodAttributeDefinitions(\ReflectionClass $reflectionClass): array
    {
        $definitions = [];

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $definitions = array_merge(
                $definitions,
                $this->definitionsFromMethodAttributes($reflectionMethod),
            );
        }

        return $definitions;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     * @return list<AbstractListenerAttribute>
     */
    private function classAttributes(\ReflectionClass $reflectionClass, string $attributeClass): array
    {
        $attributes = [];

        foreach ($reflectionClass->getAttributes($attributeClass) as $attribute) {
            $attributeInstance = $attribute->newInstance();

            if (!$attributeInstance instanceof AbstractListenerAttribute) {
                throw new InvalidListenerConfiguration('Configured listener attribute is invalid.');
            }

            $attributes[] = $attributeInstance;
        }

        return $attributes;
    }

    /**
     * @return list<ListenerDefinition>
     */
    private function definitionsFromMethodAttributes(\ReflectionMethod $reflectionMethod): array
    {
        $definitions = [];

        foreach ($this->methodAttributes($reflectionMethod) as $attribute) {
            foreach ($this->definitionsFromMethodAttribute($reflectionMethod, $attribute) as $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @return list<AsEventListener>
     */
    private function methodAttributes(\ReflectionMethod $reflectionMethod): array
    {
        $attributes = [];

        foreach ($reflectionMethod->getAttributes(AsEventListener::class) as $attribute) {
            /** @var AsEventListener $attributeInstance */
            $attributeInstance = $attribute->newInstance();
            $attributes[] = $attributeInstance;
        }

        return $attributes;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function classAttributeMethodName(
        \ReflectionClass $reflectionClass,
        AbstractListenerAttribute $attribute,
    ): string {

        if ($attribute->method !== null) {
            if (!$reflectionClass->hasMethod($attribute->method)) {
                throw new InvalidListenerConfiguration(
                    'Configured listener method does not exist.',
                );
            }

            $reflectionMethod = $reflectionClass->getMethod($attribute->method);
            $this->assertPublicMethod($reflectionMethod);

            return $attribute->method;
        }

        if ($attribute->event !== null) {
            $defaultMethodName = $this->defaultMethodName($attribute->event);

            if ($reflectionClass->hasMethod($defaultMethodName)) {
                $reflectionMethod = $reflectionClass->getMethod($defaultMethodName);
                $this->assertPublicMethod($reflectionMethod);

                return $defaultMethodName;
            }
        }

        if ($reflectionClass->hasMethod('__invoke')) {
            $reflectionMethod = $reflectionClass->getMethod('__invoke');
            $this->assertPublicMethod($reflectionMethod);

            return '__invoke';
        }

        throw new InvalidListenerConfiguration(
            'Attributed listeners must define a public target method or __invoke().',
        );
    }

    /**
     * @return list<string>
     */
    private function eventNames(
        \ReflectionMethod $reflectionMethod,
        AbstractListenerAttribute $attribute,
    ): array {

        if ($attribute->event !== null) {
            return [$attribute->event];
        }

        $eventParameter = $reflectionMethod->getParameters()[0] ?? null;

        if ($eventParameter === null) {
            throw new InvalidListenerConfiguration(
                'Attributed listeners must type-hint an event when no event name is configured.',
            );
        }

        if ($eventParameter->isVariadic()) {
            throw new InvalidListenerConfiguration(
                'Attributed listeners cannot use variadic event parameters.',
            );
        }

        $eventType = $eventParameter->getType();

        if ($eventType instanceof \ReflectionNamedType) {
            return [$this->eventNameFromNamedType($eventType)];
        }

        if ($eventType instanceof \ReflectionUnionType) {
            return $this->eventNamesFromUnionType($eventType);
        }

        throw new InvalidListenerConfiguration(
            'Attributed listeners must use named event types when inferring event names.',
        );
    }

    /**
     * @return list<string>
     */
    private function eventNamesFromUnionType(\ReflectionUnionType $eventType): array
    {
        $eventNames = [];

        foreach ($eventType->getTypes() as $namedType) {
            if (!$namedType instanceof \ReflectionNamedType) {
                throw new InvalidListenerConfiguration(
                    'Attributed listeners must use class or interface event types.',
                );
            }

            $eventNames[] = $this->eventNameFromNamedType($namedType);
        }

        return array_values(array_unique($eventNames));
    }

    private function eventNameFromNamedType(\ReflectionNamedType $namedType): string
    {
        if ($namedType->isBuiltin()) {
            throw new InvalidListenerConfiguration(
                'Attributed listeners must use class or interface event types.',
            );
        }

        return $namedType->getName();
    }

    private function assertPublicMethod(\ReflectionMethod $reflectionMethod): void
    {
        if ($reflectionMethod->isPublic()) {
            return;
        }

        throw new InvalidListenerConfiguration('Attributed listener methods must be public.');
    }

    private function defaultMethodName(string $eventName): string
    {
        $shortEventName = str_contains($eventName, '\\')
            ? (string) substr($eventName, (int) strrpos($eventName, '\\') + 1)
            : $eventName;
        $segments = preg_split('/[^a-zA-Z0-9]+/', $shortEventName) ?: [];
        $normalizedSegments = array_filter($segments, static fn (string $segment): bool => $segment !== '');

        return 'on' . implode('', array_map('ucfirst', $normalizedSegments));
    }

    private function definitionKey(ListenerDefinition $definition): string
    {
        return $definition->eventName . "\0" . $definition->methodName;
    }
}
