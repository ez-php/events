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
 *   Event::listen('App\Events\Order*', $listener, priority: 10);
 *
 * Events are dispatched synchronously by default:
 *
 *   Event::dispatch(new UserCreated($userId));
 *
 * Async dispatch pushes the event onto a queue (requires a QueueInterface set on the dispatcher):
 *
 *   Event::dispatch(new UserCreated($userId), async: true);
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
     * Register a listener for the given event class or wildcard pattern.
     *
     * @param string                                                 $eventClass Exact class-string or glob pattern
     * @param ListenerInterface|Closure(EventInterface): (bool|void) $listener
     * @param int                                                    $priority   Higher value = fires earlier (default 0)
     *
     * @return void
     */
    public static function listen(string $eventClass, ListenerInterface|Closure $listener, int $priority = 0): void
    {
        self::getDispatcher()->listen($eventClass, $listener, $priority);
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param EventInterface $event
     * @param bool           $async Push to queue instead of dispatching synchronously.
     *
     * @return void
     */
    public static function dispatch(EventInterface $event, bool $async = false): void
    {
        self::getDispatcher()->dispatch($event, $async);
    }

    /**
     * Remove all listeners for the given event class or pattern.
     *
     * @param string $eventClass Exact class-string or glob pattern to remove.
     *
     * @return void
     */
    public static function forget(string $eventClass): void
    {
        self::getDispatcher()->forget($eventClass);
    }
}
