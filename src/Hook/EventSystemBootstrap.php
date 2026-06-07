<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Hook;

use SymPress\EventDispatcher\Application\EventSystem;

final class EventSystemBootstrap
{
    private bool $dispatched = false;

    public function __construct(
        private readonly EventSystem $system,
    ) {
    }

    public function initialize(): void
    {
        $this->system->init();

        if ($this->dispatched) {
            return;
        }

        do_action(
            EventSystem::READY_HOOK,
            $this->system->getDispatcher(),
            $this->system,
        );

        $this->dispatched = true;
    }
}
