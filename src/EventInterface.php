<?php

declare(strict_types=1);

namespace EzPhp\Events;

/**
 * Interface EventInterface
 *
 * Marker interface for all dispatchable events.
 *
 * Example:
 *   class UserCreated implements EventInterface
 *   {
 *       public function __construct(public readonly int $userId) {}
 *   }
 *
 * @package EzPhp\Events
 */
interface EventInterface
{
}
