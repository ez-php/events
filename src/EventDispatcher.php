<?php

declare(strict_types=1);

namespace EzPhp\Events;

use Closure;
use EzPhp\Contracts\QueueInterface;

/**
 * Class EventDispatcher
 *
 * Synchronous event bus with support for:
 *  - Listener priority (higher priority fires first)
 *  - Wildcard patterns (fnmatch-style: `*` and `?`)
 *  - Propagation control via StoppableEventInterface or Closure returning false
 *  - Async dispatch via QueueInterface (falls back to sync when no queue is set)
 *
 * Usage:
 *   $dispatcher->listen(UserCreated::class, new SendWelcomeEmail());
 *   $dispatcher->listen(UserCreated::class, function (UserCreated $e): void { ... });
 *   $dispatcher->listen('App\Events\Order*', $listener, priority: 10);
 *   $dispatcher->dispatch(new UserCreated($userId));
 *   $dispatcher->dispatch(new UserCreated($userId), async: true);
 *
 * @package EzPhp\Events
 */
final class EventDispatcher
{
    /**
     * Registered listeners keyed by event class-string or glob pattern.
     *
     * Each entry is a list of priority-wrapped listener records.
     *
     * @var array<string, list<array{priority: int, listener: ListenerInterface|Closure(EventInterface): (bool|void)}>>
     */
    private array $listeners = [];

    /**
     * Optional queue driver used for async dispatch.
     */
    private ?QueueInterface $queue = null;

    /**
     * Set the queue driver used for async event dispatch.
     *
     * @param QueueInterface $queue
     *
     * @return void
     */
    public function setQueue(QueueInterface $queue): void
    {
        $this->queue = $queue;
    }

    /**
     * Register a listener for the given event class or wildcard pattern.
     *
     * Patterns may contain `*` (matches any sequence) and `?` (matches one character).
     * Listeners with a higher $priority fire before those with a lower value.
     * Listeners with equal priority fire in registration order.
     *
     * @param string                                                           $eventClass Exact class-string or glob pattern
     * @param ListenerInterface|Closure(EventInterface): (bool|void)           $listener
     * @param int                                                              $priority   Higher value = fires earlier (default 0)
     *
     * @return void
     */
    public function listen(string $eventClass, ListenerInterface|Closure $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][] = ['priority' => $priority, 'listener' => $listener];

        usort(
            $this->listeners[$eventClass],
            static fn (array $a, array $b): int => $b['priority'] <=> $a['priority'],
        );
    }

    /**
     * Dispatch an event to all matching listeners.
     *
     * When $async is true and a queue driver is set, the event is pushed onto
     * the queue and listeners are not invoked immediately. When no queue driver
     * is set, dispatch falls through to synchronous execution.
     *
     * Propagation stops when:
     *  - A Closure listener returns false, OR
     *  - The event implements StoppableEventInterface and isPropagationStopped() returns true.
     *
     * @param EventInterface $event
     * @param bool           $async Push to queue instead of dispatching synchronously.
     *
     * @return void
     */
    public function dispatch(EventInterface $event, bool $async = false): void
    {
        if ($async && $this->queue !== null) {
            $this->queue->push(new AsyncEventJob($event));

            return;
        }

        foreach ($this->getListenersForDispatch($event::class) as $listener) {
            if ($listener instanceof ListenerInterface) {
                $listener->handle($event);
            } else {
                /** @var bool|null $result */
                $result = $listener($event);

                if ($result === false) {
                    return;
                }
            }

            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return;
            }
        }
    }

    /**
     * Remove all listeners registered under the given key (exact class or pattern).
     *
     * @param string $eventClass Exact class-string or glob pattern to remove.
     *
     * @return void
     */
    public function forget(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
    }

    /**
     * Return all listeners that would be invoked for the given event class,
     * ordered by priority (descending). Includes wildcard matches.
     *
     * @param  string                                                                $eventClass
     *
     * @return list<ListenerInterface|Closure(EventInterface): (bool|void)>
     */
    public function getListeners(string $eventClass): array
    {
        return $this->getListenersForDispatch($eventClass);
    }

    /**
     * Collect, merge, and sort all listeners applicable to the given event class.
     *
     * Exact-key listeners are collected first, then wildcard-pattern listeners.
     * The combined list is sorted by priority descending (stable within same priority).
     *
     * @param  string $eventClass
     *
     * @return list<ListenerInterface|Closure(EventInterface): (bool|void)>
     */
    private function getListenersForDispatch(string $eventClass): array
    {
        /** @var list<array{priority: int, listener: ListenerInterface|Closure(EventInterface): (bool|void)}> $records */
        $records = $this->listeners[$eventClass] ?? [];

        // Anonymous classes contain null bytes in their generated names.
        // fnmatch() rejects null bytes in the filename argument, so we cannot
        // use it directly for anonymous classes. We handle this by:
        //   - Always matching the bare '*' pattern (it means "any event").
        //   - Skipping all other fnmatch-based matching for anonymous classes.
        $hasNullBytes = str_contains($eventClass, "\0");

        foreach ($this->listeners as $key => $keyRecords) {
            if ($key === $eventClass) {
                continue;
            }

            if (!str_contains($key, '*') && !str_contains($key, '?')) {
                continue;
            }

            $matches = $key === '*'
                || (!$hasNullBytes && fnmatch($key, $eventClass, FNM_NOESCAPE));

            if ($matches) {
                foreach ($keyRecords as $record) {
                    $records[] = $record;
                }
            }
        }

        usort($records, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return array_map(static fn (array $record): ListenerInterface|Closure => $record['listener'], $records);
    }
}
