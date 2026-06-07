# Event Dispatcher

Standalone WordPress MU-plugin for Symfony-style class-based events, listeners, and subscribers on top of native WordPress hooks and filters.

## Requirements

- WordPress 6.9
- PHP 8.5

## Design Goals

- Symfony-like listener and subscriber API
- PSR-14 compliant dispatcher and listener provider contracts
- Class-based WordPress action and filter events
- Immutable event value objects
- Native WordPress hook bridge with one callback registration per event class
- Container-friendly bootstrap for `SymPress\Kernel\App`
- Enterprise-focused QA with PHPUnit, PHPStan, PHPCS, and PHP CS Fixer

## Installation

1. Place the package in `wp-content/mu-plugins/event-dispatcher/`.
2. Ensure Composer autoloading is available.
3. Add a root MU loader file because WordPress does not autoload subdirectories.

```php
<?php

declare(strict_types=1);

require WPMU_PLUGIN_DIR . '/event-dispatcher/event-dispatcher.php';
```

## Registering Subscribers

```php
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Contract\EventSubscriberInterface;
use SymPress\EventDispatcher\Event\AbstractFilterEvent;

final readonly class UploadMimesEvent extends AbstractFilterEvent
{
    public function __construct(
        array $mimes,
        public int $userId,
    )
    {
        parent::__construct($mimes, [$mimes, $userId]);
    }

    public static function hookName(): string
    {
        return 'upload_mimes';
    }

    public static function acceptedArgs(): int
    {
        return 2;
    }

    public static function fromHookArguments(array $arguments): static
    {
        return new self(
            (array) ($arguments[0] ?? []),
            (int) ($arguments[1] ?? 0),
        );
    }

    /**
     * @return array<string, string>
     */
    public function mimes(): array
    {
        /** @var array<string, string> $mimes */
        $mimes = $this->value();

        return $mimes;
    }

    public function withAllowed(string $extension, string $mimeType): self
    {
        $mimes = $this->mimes();
        $mimes[$extension] = $mimeType;

        return new self($mimes, $this->userId);
    }
}

final class UploadSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UploadMimesEvent::class => ['allowSvg', 100],
        ];
    }

    public function allowSvg(UploadMimesEvent $event): UploadMimesEvent
    {
        return $event->withAllowed('svg', 'image/svg+xml');
    }
}

add_action(
    EventSystem::REGISTER_HOOK,
    static function ($dispatcher): void {
        $dispatcher->addSubscriber(new UploadSubscriber());
    },
);
```

## Filter Strategy

Filter sind bereits implementiert.

- Das erste native Filter-Argument ist der Payload des Events.
- Filter-Events sind immutable Value Objects.
- Listener mutieren nie in-place, sondern geben bei Änderungen eine neue Event-Instanz zurück.
- Der letzte Event-Stand wird über `toHookResult()` zurück in den nativen WordPress-Filter geschrieben.

Kurz gesagt: `apply_filters()` bleibt nach außen WordPress-kompatibel, intern arbeitest du aber mit sauberen immutable Events.

## Registering Attribute-Based Listeners

```php
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Attribute\AsEventListener;
use SymPress\EventDispatcher\Attribute\AsEventSubscriber;

#[AsEventSubscriber(event: UploadMimesEvent::class, method: 'allowSvg', priority: 100)]
final class UploadListener
{
    public function allowSvg(UploadMimesEvent $event): UploadMimesEvent
    {
        return $event->withAllowed('svg', 'image/svg+xml');
    }

    #[AsEventListener(priority: 0)]
    public function allowWebp(UploadMimesEvent $event): UploadMimesEvent
    {
        return $event->withAllowed('webp', 'image/webp');
    }
}

add_action(
    EventSystem::REGISTER_HOOK,
    static function ($dispatcher): void {
        $dispatcher->register(new UploadListener());
    },
);
```

## Use Cases

### 1. WordPress Filter: MIME Types erweitern

```php
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Event\AbstractFilterEvent;

final readonly class UploadMimesEvent extends AbstractFilterEvent
{
    /**
     * @param array<string, string> $mimes
     */
    public function __construct(
        array $mimes,
        public int $userId,
    ) {
        parent::__construct($mimes, [$mimes, $userId]);
    }

    public static function hookName(): string
    {
        return 'upload_mimes';
    }

    public static function acceptedArgs(): int
    {
        return 2;
    }

    public static function fromHookArguments(array $arguments): static
    {
        return new self(
            (array) ($arguments[0] ?? []),
            (int) ($arguments[1] ?? 0),
        );
    }

    /**
     * @return array<string, string>
     */
    public function mimes(): array
    {
        /** @var array<string, string> $mimes */
        $mimes = $this->value();

        return $mimes;
    }

    public function withAllowed(string $extension, string $mimeType): self
    {
        $mimes = $this->mimes();
        $mimes[$extension] = $mimeType;

        return new self($mimes, $this->userId);
    }
}

add_action(
    EventSystem::REGISTER_HOOK,
    static function ($dispatcher): void {
        $dispatcher->addListener(
            UploadMimesEvent::class,
            static fn (UploadMimesEvent $event): UploadMimesEvent => $event->withAllowed(
                'svg',
                'image/svg+xml',
            ),
            100,
        );
    },
);
```

### 2. WordPress Action: Typed `save_post`

```php
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Event\AbstractActionEvent;

final readonly class SavePostEvent extends AbstractActionEvent
{
    public function __construct(
        public int $postId,
        public bool $update,
    ) {
        parent::__construct([$postId, $update]);
    }

    public static function hookName(): string
    {
        return 'save_post';
    }

    public static function acceptedArgs(): int
    {
        return 2;
    }

    public static function fromHookArguments(array $arguments): static
    {
        return new self(
            (int) ($arguments[0] ?? 0),
            (bool) ($arguments[1] ?? false),
        );
    }
}

add_action(
    EventSystem::REGISTER_HOOK,
    static function ($dispatcher): void {
        $dispatcher->addListener(
            SavePostEvent::class,
            static function (SavePostEvent $event): void {
                if (!$event->update) {
                    return;
                }

                error_log('Updated post: ' . $event->postId);
            },
        );
    },
);
```

### 3. Attribute-Based Hook Service

```php
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Attribute\AsEventListener;
use SymPress\EventDispatcher\Attribute\AsEventSubscriber;

#[AsEventSubscriber(event: SavePostEvent::class, method: 'onSavePost')]
final class EditorialWorkflowListener
{
    public function onSavePost(SavePostEvent $event): void
    {
        if (!$event->update) {
            return;
        }

        error_log('Editorial workflow for post ' . $event->postId);
    }

    #[AsEventListener(priority: 100)]
    public function allowSvg(UploadMimesEvent $event): UploadMimesEvent
    {
        return $event->withAllowed('svg', 'image/svg+xml');
    }
}

add_action(
    EventSystem::REGISTER_HOOK,
    static function ($dispatcher): void {
        $dispatcher->register(new EditorialWorkflowListener());
    },
);
```

### 4. Pure Domain Event Without WordPress Hook

```php
use SymPress\EventDispatcher\Application\EventSystem;
use SymPress\EventDispatcher\Event\AbstractEvent;

final readonly class OrderPaidEvent extends AbstractEvent
{
    public function __construct(
        public int $orderId,
        public string $paymentReference,
    ) {
    }
}

$dispatcher = EventSystem::getInstance()->getDispatcher();

$dispatcher->addListener(
    OrderPaidEvent::class,
    static function (OrderPaidEvent $event): void {
        error_log(
            sprintf(
                'Order %d paid with reference %s',
                $event->orderId,
                $event->paymentReference,
            ),
        );
    },
);

$dispatcher->dispatch(new OrderPaidEvent(42, 'PAY-123'));
```

### 5. Invokable Attribute Listener for Domain Events

```php
use SymPress\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrderPaidEvent::class, priority: 50)]
final class SendInvoiceListener
{
    public function __invoke(OrderPaidEvent $event): void
    {
        error_log('Send invoice for order ' . $event->orderId);
    }
}

add_action(
    EventSystem::READY_HOOK,
    static function ($dispatcher): void {
        $dispatcher->register(new SendInvoiceListener());
    },
);
```

## Runtime Model

- `EventSystem` owns the singleton bootstrap lifecycle.
- `HookEventDispatcher` implements PSR-14 dispatching and listener resolution plus the WordPress hook bridge.
- `HookEventDispatcher::register()` resolves `AsEventListener` and `AsEventSubscriber` on classes and methods.
- `AbstractActionEvent` maps native actions to typed event objects.
- `AbstractFilterEvent` maps native filters to immutable event value objects; listeners return a new event instance when they change data.
- Filter listeners should return the same event unchanged or a new event instance of the same class.
- Subscribers follow the Symfony event subscriber format.

## Development

```bash
composer test
composer cs:analyze
composer cs
```
