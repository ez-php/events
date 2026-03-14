<?php

declare(strict_types=1);

namespace EzPhp\Events;

use Closure;

/**
 * Class EventDispatcher
 *
 * Synchronous event bus. Listeners are called in registration order.
 *
 * Usage:
 *   $dispatcher->listen(UserCreated::class, new SendWelcomeEmail());
 *   $dispatcher->listen(UserCreated::class, function (UserCreated $e): void { ... });
 *   $dispatcher->dispatch(new UserCreated($userId));
 *
 * @package EzPhp\Events
 */
final class EventDispatcher
{
    /**
     * @var array<string, list<ListenerInterface|Closure(EventInterface): void>>
     */
    private array $listeners = [];

    /**
     * Register a listener for the given event class.
     *
     * @param class-string<EventInterface>                    $eventClass
     * @param ListenerInterface|Closure(EventInterface): void $listener
     */
    public function listen(string $eventClass, ListenerInterface|Closure $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public function dispatch(EventInterface $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            if ($listener instanceof ListenerInterface) {
                $listener->handle($event);
            } else {
                $listener($event);
            }
        }
    }

    /**
     * Remove all listeners for the given event class.
     *
     * @param class-string<EventInterface> $eventClass
     */
    public function forget(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
    }

    /**
     * Return all listeners registered for the given event class.
     *
     * @param  class-string<EventInterface>                           $eventClass
     *
     * @return list<ListenerInterface|Closure(EventInterface): void>
     */
    public function getListeners(string $eventClass): array
    {
        return $this->listeners[$eventClass] ?? [];
    }
}
