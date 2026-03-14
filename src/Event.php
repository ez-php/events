<?php

declare(strict_types=1);

namespace EzPhp\Events;

use Closure;

/**
 * Class Event
 *
 * Static facade for the EventDispatcher.
 *
 * Listeners can be registered from any service provider's boot() method:
 *
 *   Event::listen(UserCreated::class, new SendWelcomeEmail());
 *   Event::listen(UserCreated::class, function (UserCreated $event): void { ... });
 *
 * Events are dispatched synchronously:
 *
 *   Event::dispatch(new UserCreated($userId));
 *
 * @package EzPhp\Events
 */
final class Event
{
    private static ?EventDispatcher $dispatcher = null;

    // ─── Static dispatcher management ────────────────────────────────────────

    /**
     * @param EventDispatcher $dispatcher
     *
     * @return void
     */
    public static function setDispatcher(EventDispatcher $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    /**
     * @return EventDispatcher
     */
    public static function getDispatcher(): EventDispatcher
    {
        if (self::$dispatcher === null) {
            self::$dispatcher = new EventDispatcher();
        }

        return self::$dispatcher;
    }

    /**
     * Reset the static dispatcher (useful in tests).
     */
    public static function resetDispatcher(): void
    {
        self::$dispatcher = null;
    }

    // ─── Static facade ────────────────────────────────────────────────────────

    /**
     * Register a listener for the given event class.
     *
     * @param class-string<EventInterface>                    $eventClass
     * @param ListenerInterface|Closure(EventInterface): void $listener
     */
    public static function listen(string $eventClass, ListenerInterface|Closure $listener): void
    {
        self::getDispatcher()->listen($eventClass, $listener);
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public static function dispatch(EventInterface $event): void
    {
        self::getDispatcher()->dispatch($event);
    }

    /**
     * Remove all listeners for the given event class.
     *
     * @param class-string<EventInterface> $eventClass
     */
    public static function forget(string $eventClass): void
    {
        self::getDispatcher()->forget($eventClass);
    }
}
