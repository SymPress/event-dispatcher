<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Dispatcher;

final class ListenerIdentifierFactory
{
    public function create(callable $listener): string
    {
        if ($listener instanceof \Closure) {
            return spl_object_hash($listener);
        }

        if (is_string($listener)) {
            return $listener;
        }

        if (is_array($listener)) {
            $target = $listener[0] ?? null;
            $method = $listener[1] ?? null;

            if (!is_string($method) || $method === '') {
                throw new \InvalidArgumentException(
                    'Listener array callbacks must define a method name.',
                );
            }

            if (is_object($target)) {
                return spl_object_hash($target) . '::' . $method;
            }

            if (is_string($target) && $target !== '') {
                return $target . '::' . $method;
            }
        }

        if (is_object($listener)) {
            return spl_object_hash($listener) . '::__invoke';
        }

        throw new \InvalidArgumentException('Listener callable could not be identified.');
    }
}
