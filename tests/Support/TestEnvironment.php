<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Tests\Support {

    final class HookState
    {
        /** @var array<string, array<int, array<string, array{callback: callable, accepted_args: int}>>> */
        public static array $hooks = [];

        /** @var list<string> */
        public static array $currentFilters = [];

        public static function reset(): void
        {
            self::$hooks = [];
            self::$currentFilters = [];
        }
    }
}

namespace {

    use SymPress\EventDispatcher\Tests\Support\HookState;

    if (!function_exists('wp_test_listener_identifier')) {
        function wp_test_listener_identifier(callable $callback): string
        {
            if ($callback instanceof Closure) {
                return spl_object_hash($callback);
            }

            if (is_string($callback)) {
                return $callback;
            }

            if (is_array($callback)) {
                $target = $callback[0] ?? null;
                $method = $callback[1] ?? null;

                if (is_object($target) && is_string($method) && $method !== '') {
                    return spl_object_hash($target) . '::' . $method;
                }

                if (is_string($target) && $target !== '' && is_string($method) && $method !== '') {
                    return $target . '::' . $method;
                }
            }

            if (is_object($callback)) {
                return spl_object_hash($callback) . '::__invoke';
            }

            throw new InvalidArgumentException('The callback could not be identified.');
        }
    }

    if (!function_exists('add_action')) {
        function add_action(
            string $hook,
            callable $callback,
            int $priority = 10,
            int $acceptedArgs = 1,
        ): void {
            HookState::$hooks[$hook][$priority][wp_test_listener_identifier($callback)] = [
                'callback' => $callback,
                'accepted_args' => $acceptedArgs,
            ];
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter(
            string $hook,
            callable $callback,
            int $priority = 10,
            int $acceptedArgs = 1,
        ): void {
            add_action($hook, $callback, $priority, $acceptedArgs);
        }
    }

    if (!function_exists('remove_action')) {
        function remove_action(
            string $hook,
            callable $callback,
            int $priority = 10,
        ): void {
            unset(HookState::$hooks[$hook][$priority][wp_test_listener_identifier($callback)]);

            if ((HookState::$hooks[$hook][$priority] ?? []) !== []) {
                return;
            }

            unset(HookState::$hooks[$hook][$priority]);

            if ((HookState::$hooks[$hook] ?? []) !== []) {
                return;
            }

            unset(HookState::$hooks[$hook]);
        }
    }

    if (!function_exists('remove_filter')) {
        function remove_filter(
            string $hook,
            callable $callback,
            int $priority = 10,
        ): void {
            remove_action($hook, $callback, $priority);
        }
    }

    if (!function_exists('do_action')) {
        function do_action(string $hook, mixed ...$args): void
        {
            $callbacksByPriority = HookState::$hooks[$hook] ?? [];

            if ($callbacksByPriority === []) {
                return;
            }

            ksort($callbacksByPriority);
            HookState::$currentFilters[] = $hook;

            try {
                foreach ($callbacksByPriority as $callbacks) {
                    foreach ($callbacks as $callback) {
                        $acceptedArgs = $callback['accepted_args'];
                        $callbackArgs = array_slice($args, 0, $acceptedArgs);

                        ($callback['callback'])(...$callbackArgs);
                    }
                }
            } finally {
                array_pop(HookState::$currentFilters);
            }
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            $callbacksByPriority = HookState::$hooks[$hook] ?? [];

            if ($callbacksByPriority === []) {
                return $value;
            }

            ksort($callbacksByPriority);
            HookState::$currentFilters[] = $hook;

            try {
                foreach ($callbacksByPriority as $callbacks) {
                    foreach ($callbacks as $callback) {
                        $acceptedArgs = $callback['accepted_args'];
                        $callbackArgs = array_slice(array_merge([$value], $args), 0, $acceptedArgs);

                        $value = ($callback['callback'])(...$callbackArgs);
                    }
                }
            } finally {
                array_pop(HookState::$currentFilters);
            }

            return $value;
        }
    }

    if (!function_exists('current_filter')) {
        function current_filter(): string
        {
            $currentFilter = end(HookState::$currentFilters);

            if (is_string($currentFilter)) {
                return $currentFilter;
            }

            return '';
        }
    }
}
