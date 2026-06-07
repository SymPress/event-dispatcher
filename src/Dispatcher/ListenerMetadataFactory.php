<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Dispatcher;

use SymPress\EventDispatcher\Value\ListenerMetadata;

final class ListenerMetadataFactory
{
    private readonly ListenerIdentifierFactory $listenerIdentifierFactory;

    public function __construct(?ListenerIdentifierFactory $listenerIdentifierFactory = null)
    {
        $this->listenerIdentifierFactory = $listenerIdentifierFactory ?? new ListenerIdentifierFactory();
    }

    public function create(
        string $eventName,
        callable $listener,
        int $priority,
        int $sequence,
    ): ListenerMetadata {

        if ($eventName === '') {
            throw new \InvalidArgumentException('Event names cannot be empty.');
        }

        $closure = \Closure::fromCallable($listener);
        $reflection = new \ReflectionFunction($closure);

        if ($reflection->getNumberOfParameters() !== 1 || $reflection->isVariadic()) {
            throw new \InvalidArgumentException(
                'PSR-14 listeners must declare exactly one non-variadic event parameter.',
            );
        }

        return new ListenerMetadata(
            $eventName,
            $closure,
            $this->listenerIdentifierFactory->create($listener),
            $priority,
            $sequence,
        );
    }
}
