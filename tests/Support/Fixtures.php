<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Tests\Support;

use SymPress\EventDispatcher\Attribute\AsEventListener;
use SymPress\EventDispatcher\Attribute\AsEventSubscriber;
use SymPress\EventDispatcher\Contract\EventSubscriberInterface;
use SymPress\EventDispatcher\Event\AbstractActionEvent;
use SymPress\EventDispatcher\Event\AbstractEvent;
use SymPress\EventDispatcher\Event\AbstractFilterEvent;
use Psr\EventDispatcher\StoppableEventInterface;

interface DemoEventMarkerInterface
{
}

readonly class DemoEvent extends AbstractEvent implements DemoEventMarkerInterface
{
    public function __construct(public string $name = 'demo')
    {
    }

    public function withName(string $name): self
    {
        return new self($name);
    }
}

final readonly class ChildDemoEvent extends DemoEvent
{
}

final class MutableStoppableEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function stop(): void
    {
        $this->propagationStopped = true;
    }

    #[\Override]
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}

final class CountingSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public array $calls = [];

    public static function getSubscribedEvents(): array
    {
        return [
            DemoEvent::class => [
                ['onHighPriority', 50],
                ['onLowPriority', -50],
            ],
        ];
    }

    public function onHighPriority(DemoEvent $event): void
    {
        $this->calls[] = 'high:' . $event->name;
    }

    public function onLowPriority(DemoEvent $event): void
    {
        $this->calls[] = 'low:' . $event->name;
    }
}

final class StopPropagationSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public array $calls = [];

    public static function getSubscribedEvents(): array
    {
        return [
            MutableStoppableEvent::class => [
                ['stopDispatching', 100],
                ['shouldNeverRun', -100],
            ],
        ];
    }

    public function stopDispatching(MutableStoppableEvent $event): void
    {
        $this->calls[] = 'stop';
        $event->stop();
    }

    public function shouldNeverRun(MutableStoppableEvent $event): void
    {
        $this->calls[] = 'never';
    }
}

final class InvalidConfigurationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DemoEvent::class => [123],
        ];
    }
}

#[AsEventListener(event: DemoEvent::class, priority: 60)]
final class InvokableAttributedListener
{
    /** @var list<string> */
    public array $calls = [];

    public function __invoke(DemoEvent $event): void
    {
        $this->calls[] = 'invoke:' . $event->name;
    }
}

#[AsEventListener(event: DemoEvent::class, priority: 30)]
final class DefaultMethodAttributedListener
{
    /** @var list<string> */
    public array $calls = [];

    public function onDemoEvent(DemoEvent $event): void
    {
        $this->calls[] = 'default:' . $event->name;
    }
}

#[AsEventSubscriber(event: DemoEvent::class, method: 'onHighPriority', priority: 40)]
final class AttributedSubscriberService
{
    /** @var list<string> */
    public array $calls = [];

    public function onHighPriority(DemoEvent $event): void
    {
        $this->calls[] = 'attribute-high:' . $event->name;
    }

    #[AsEventListener(priority: -40)]
    public function onLowPriority(DemoEvent $event): void
    {
        $this->calls[] = 'attribute-low:' . $event->name;
    }
}

final class HybridSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public array $calls = [];

    public static function getSubscribedEvents(): array
    {
        return [
            DemoEvent::class => ['onInterfaceListener', 0],
        ];
    }

    public function onInterfaceListener(DemoEvent $event): void
    {
        $this->calls[] = 'interface:' . $event->name;
    }

    #[AsEventListener(priority: 20)]
    public function onAttributeListener(DemoEvent $event): void
    {
        $this->calls[] = 'attribute:' . $event->name;
    }
}

final class InvalidAttributedListener
{
    #[AsEventListener]
    public function onNothing(): void
    {
    }
}

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

final readonly class AllowedMimeTypesEvent extends AbstractFilterEvent
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

final class HookSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public array $actions = [];

    /** @var list<int> */
    public array $filterUsers = [];

    public static function getSubscribedEvents(): array
    {
        return [
            SavePostEvent::class => 'onSavePost',
            AllowedMimeTypesEvent::class => [
                ['allowSvg', 100],
                ['allowWebp', 0],
            ],
        ];
    }

    public function onSavePost(SavePostEvent $event): void
    {
        $this->actions[] = sprintf('%d:%d', $event->postId, $event->update ? 1 : 0);
    }

    public function allowSvg(AllowedMimeTypesEvent $event): AllowedMimeTypesEvent
    {
        $this->filterUsers[] = $event->userId;

        return $event->withAllowed('svg', 'image/svg+xml');
    }

    public function allowWebp(AllowedMimeTypesEvent $event): AllowedMimeTypesEvent
    {
        $this->filterUsers[] = $event->userId;

        return $event->withAllowed('webp', 'image/webp');
    }
}

#[AsEventSubscriber(event: SavePostEvent::class, method: 'onSavePost')]
final class AttributedHookSubscriber
{
    /** @var list<string> */
    public array $actions = [];

    /** @var list<int> */
    public array $filterUsers = [];

    public function onSavePost(SavePostEvent $event): void
    {
        $this->actions[] = sprintf('%d:%d', $event->postId, $event->update ? 1 : 0);
    }

    #[AsEventListener(priority: 100)]
    public function allowSvg(AllowedMimeTypesEvent $event): AllowedMimeTypesEvent
    {
        $this->filterUsers[] = $event->userId;

        return $event->withAllowed('svg', 'image/svg+xml');
    }

    #[AsEventListener(priority: 0)]
    public function allowWebp(AllowedMimeTypesEvent $event): AllowedMimeTypesEvent
    {
        $this->filterUsers[] = $event->userId;

        return $event->withAllowed('webp', 'image/webp');
    }
}
