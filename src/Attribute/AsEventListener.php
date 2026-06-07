<?php

declare(strict_types=1);

namespace SymPress\EventDispatcher\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class AsEventListener extends AbstractListenerAttribute
{
}
