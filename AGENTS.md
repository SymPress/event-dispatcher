# SymPress Event Dispatcher

## Scope and entry points

- Read the `README.md` Runtime Model before changing dispatch behavior.
- `src/Application/EventSystem.php` owns bootstrap order and the public registration hooks.
- `src/Dispatcher/HookEventDispatcher.php` is the PSR-14/WordPress bridge.
- `src/Dispatcher/ListenerDefinitionResolver.php` owns subscriber and attribute discovery.

## Verification

- Fast behavior check: `composer tests`.
- Full required check: `composer qa`.
- Add or update a focused test in `tests/Unit/Dispatcher` for listener resolution or hook-bridge changes.

## Invariants

- Register each native WordPress hook callback once per hook-event class.
- Preserve listener priority, inherited event listeners, and stoppable-event behavior.
- Action events return the native action result; filter events accept at least one argument and return the event value.
- A filter listener may return the same event or a new instance of the same event class. Ignore other return values.
- Keep subscriber configuration compatible with the documented Symfony-style formats.

## Cross-repository impact and done

- The kernel discovers `EventDispatcherBundle` and `event-dispatcher/event-dispatcher.php` through `extra.kernel`.
- Public hook names and the `EventSystem::*_HOOK` constants are cross-package contracts.
- A change is done when the relevant bridge/resolver test and `composer qa` pass and README examples still use existing scripts.
