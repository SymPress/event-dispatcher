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
            $target = $listener[0];
            $method = $listener[1];

            if (is_object($target)) {
                return spl_object_hash($target) . '::' . $method;
            }

            return $target . '::' . $method;
        }

        if (is_object($listener)) {
            return spl_object_hash($listener) . '::__invoke';
        }

        throw new \InvalidArgumentException('Listener callable could not be identified.');
    }
}
