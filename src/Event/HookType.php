<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Event;

enum HookType: string
{
    case Action = 'action';
    case Filter = 'filter';
}
