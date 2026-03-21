<?php

declare(strict_types=1);

namespace Tests\Events;

use EzPhp\Contracts\JobInterface;
use EzPhp\Contracts\QueueInterface;
use EzPhp\Events\AsyncEventJob;
use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Events\ListenerInterface;
use EzPhp\Events\StoppableEventInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class EventDispatcherTest
 *
 * @package Tests\Events
 */
#[CoversClass(EventDispatcher::class)]
#[UsesClass(AsyncEventJob::class)]
final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new EventDispatcher();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return EventInterface
     */
    private function makeEvent(): EventInterface
    {
        return new class () implements EventInterface {
        };
    }

    /**
     * @return StoppableEventInterface
     */
    private function makeStoppableEvent(): StoppableEventInterface
    {
        return new class () implements StoppableEventInterface {
            private bool $stopped = false;

            /**
             * @return bool
             */
            public function isPropagationStopped(): bool
            {
                return $this->stopped;
            }

            /**
             * @return void
             */
            public function stopPropagation(): void
            {
                $this->stopped = true;
            }
        };
    }

    // ─── listen / dispatch ────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_dispatch_calls_listener_interface(): void
    {
        $event = $this->makeEvent();
        $listener = new class () implements ListenerInterface {
            public bool $called = false;

            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
                $this->called = true;
            }
        };

        $this->dispatcher->listen($event::class, $listener);
        $this->dispatcher->dispatch($event);

        $this->assertTrue($listener->called);
    }

    /**
     * @return void
     */
    public function test_dispatch_calls_closure_listener(): void
    {
        $listener = new class () implements ListenerInterface {
            public bool $called = false;

            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
                $this->called = true;
            }
        };

        $event = $this->makeEvent();
        $this->dispatcher->listen($event::class, function (EventInterface $e) use ($listener): void {
            $listener->handle($e);
        });

        $this->dispatcher->dispatch($event);

        $this->assertTrue($listener->called);
    }

    /**
     * @return void
     */
    public function test_dispatch_passes_event_to_listener(): void
    {
        $event = $this->makeEvent();
        $container = new class () implements ListenerInterface {
            public ?EventInterface $received = null;

            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
                $this->received = $event;
            }
        };

        $this->dispatcher->listen($event::class, $container);
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $container->received);
    }

    /**
     * @return void
     */
    public function test_dispatch_calls_multiple_listeners_in_order(): void
    {
        $event = $this->makeEvent();
        $tracker = new class () implements ListenerInterface {
            /** @var list<int> */
            public array $order = [];

            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
            }
        };

        $this->dispatcher->listen($event::class, function (EventInterface $e) use ($tracker): void {
            $tracker->order[] = 1;
        });
        $this->dispatcher->listen($event::class, function (EventInterface $e) use ($tracker): void {
            $tracker->order[] = 2;
        });
        $this->dispatcher->listen($event::class, function (EventInterface $e) use ($tracker): void {
            $tracker->order[] = 3;
        });

        $this->dispatcher->dispatch($event);

        $this->assertSame([1, 2, 3], $tracker->order);
    }

    /**
     * @return void
     */
    public function test_dispatch_does_nothing_when_no_listeners(): void
    {
        $this->expectNotToPerformAssertions();

        $this->dispatcher->dispatch($this->makeEvent());
    }

    /**
     * @return void
     */
    public function test_dispatch_only_calls_listeners_for_matching_event(): void
    {
        $eventA = new class () implements EventInterface {
        };
        $eventB = new class () implements EventInterface {
        };

        $listenerA = new class () implements ListenerInterface {
            public bool $called = false;

            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
                $this->called = true;
            }
        };
        $listenerB = new class () implements ListenerInterface {
            public bool $called = false;

            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
                $this->called = true;
            }
        };

        $this->dispatcher->listen($eventA::class, $listenerA);
        $this->dispatcher->listen($eventB::class, $listenerB);

        $this->dispatcher->dispatch($eventA);

        $this->assertTrue($listenerA->called);
        $this->assertFalse($listenerB->called);
    }

    // ─── forget ───────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_forget_removes_all_listeners_for_event(): void
    {
        $event = $this->makeEvent();
        $listener = new class () implements ListenerInterface {
            public bool $called = false;

            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
                $this->called = true;
            }
        };

        $this->dispatcher->listen($event::class, $listener);
        $this->dispatcher->forget($event::class);
        $this->dispatcher->dispatch($event);

        $this->assertFalse($listener->called);
    }

    /**
     * @return void
     */
    public function test_forget_on_unregistered_event_does_not_throw(): void
    {
        $this->expectNotToPerformAssertions();

        $event = $this->makeEvent();
        $this->dispatcher->forget($event::class);
    }

    // ─── getListeners ─────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_listeners_returns_empty_for_unknown_event(): void
    {
        $event = $this->makeEvent();

        $this->assertSame([], $this->dispatcher->getListeners($event::class));
    }

    /**
     * @return void
     */
    public function test_get_listeners_returns_registered_listeners(): void
    {
        $event = $this->makeEvent();
        $listener = new class () implements ListenerInterface {
            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
            }
        };

        $this->dispatcher->listen($event::class, $listener);

        $this->assertCount(1, $this->dispatcher->getListeners($event::class));
        $this->assertSame($listener, $this->dispatcher->getListeners($event::class)[0]);
    }

    // ─── Propagation control ──────────────────────────────────────────────────

    /**
     * Listener calling stopPropagation() halts subsequent listeners.
     *
     * @return void
     */
    public function test_stop_propagation_halts_subsequent_listeners(): void
    {
        $event = $this->makeStoppableEvent();

        $firstCalled = false;
        $secondCalled = false;

        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$firstCalled): void {
            $firstCalled = true;
            assert($e instanceof StoppableEventInterface);
            $e->stopPropagation();
        });
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$secondCalled): void {
            $secondCalled = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertTrue($firstCalled);
        $this->assertFalse($secondCalled);
    }

    /**
     * Closure returning false halts subsequent listeners.
     *
     * @return void
     */
    public function test_closure_returning_false_halts_subsequent_listeners(): void
    {
        $event = $this->makeEvent();

        $secondCalled = false;

        $this->dispatcher->listen($event::class, function (EventInterface $e): bool {
            return false;
        });
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$secondCalled): void {
            $secondCalled = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertFalse($secondCalled);
    }

    /**
     * Listener returning non-false continues normally.
     *
     * @return void
     */
    public function test_closure_returning_non_false_continues_dispatch(): void
    {
        $event = $this->makeEvent();

        $secondCalled = false;

        $this->dispatcher->listen($event::class, function (EventInterface $e): bool {
            return true;
        });
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$secondCalled): void {
            $secondCalled = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertTrue($secondCalled);
    }

    /**
     * Returning false from a closure stops propagation on non-stoppable events too.
     *
     * @return void
     */
    public function test_closure_false_stops_non_stoppable_event(): void
    {
        $event = $this->makeEvent();

        $thirdCalled = false;

        $this->dispatcher->listen($event::class, function (EventInterface $e): void {
            // first, no-op
        });
        $this->dispatcher->listen($event::class, function (EventInterface $e): bool {
            return false;
        });
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$thirdCalled): void {
            $thirdCalled = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertFalse($thirdCalled);
    }

    /**
     * With two listeners; first stops propagation: second never fires.
     *
     * @return void
     */
    public function test_two_listeners_first_stops_second_never_fires(): void
    {
        $event = $this->makeStoppableEvent();

        $calls = [];

        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$calls): void {
            $calls[] = 'first';
            assert($e instanceof StoppableEventInterface);
            $e->stopPropagation();
        });
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$calls): void {
            $calls[] = 'second';
        });

        $this->dispatcher->dispatch($event);

        $this->assertSame(['first'], $calls);
    }

    // ─── Priority ─────────────────────────────────────────────────────────────

    /**
     * Higher priority listener fires before lower priority.
     *
     * @return void
     */
    public function test_higher_priority_fires_first(): void
    {
        $event = $this->makeEvent();
        $order = [];

        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$order): void {
            $order[] = 'low';
        }, 1);
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$order): void {
            $order[] = 'high';
        }, 10);

        $this->dispatcher->dispatch($event);

        $this->assertSame(['high', 'low'], $order);
    }

    /**
     * Equal priority preserves registration order.
     *
     * @return void
     */
    public function test_equal_priority_preserves_registration_order(): void
    {
        $event = $this->makeEvent();
        $order = [];

        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$order): void {
            $order[] = 'first';
        }, 5);
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$order): void {
            $order[] = 'second';
        }, 5);
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$order): void {
            $order[] = 'third';
        }, 5);

        $this->dispatcher->dispatch($event);

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    /**
     * Negative priority fires last.
     *
     * @return void
     */
    public function test_negative_priority_fires_last(): void
    {
        $event = $this->makeEvent();
        $order = [];

        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$order): void {
            $order[] = 'negative';
        }, -1);
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$order): void {
            $order[] = 'default';
        }, 0);

        $this->dispatcher->dispatch($event);

        $this->assertSame(['default', 'negative'], $order);
    }

    /**
     * getListeners() returns listeners in priority order.
     *
     * @return void
     */
    public function test_get_listeners_returns_priority_order(): void
    {
        $event = $this->makeEvent();

        $listenerLow = new class () implements ListenerInterface {
            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
            }
        };
        $listenerHigh = new class () implements ListenerInterface {
            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
            }
        };

        $this->dispatcher->listen($event::class, $listenerLow, 1);
        $this->dispatcher->listen($event::class, $listenerHigh, 10);

        $listeners = $this->dispatcher->getListeners($event::class);

        $this->assertSame($listenerHigh, $listeners[0]);
        $this->assertSame($listenerLow, $listeners[1]);
    }

    // ─── Wildcard listeners ───────────────────────────────────────────────────

    /**
     * Glob pattern matches OrderPlaced and OrderShipped but not UserCreated.
     *
     * @return void
     */
    public function test_wildcard_matches_event_class(): void
    {
        $matched = [];
        $this->dispatcher->listen('Tests\Events\Order*', function (EventInterface $e) use (&$matched): void {
            $matched[] = $e::class;
        });

        $this->dispatcher->dispatch(new OrderPlaced());
        $this->dispatcher->dispatch(new OrderShipped());
        $this->dispatcher->dispatch(new UserCreated());

        $this->assertContains(OrderPlaced::class, $matched);
        $this->assertContains(OrderShipped::class, $matched);
        $this->assertNotContains(UserCreated::class, $matched);
    }

    /**
     * Glob pattern does NOT match unrelated event class.
     *
     * @return void
     */
    public function test_wildcard_does_not_match_unrelated_event(): void
    {
        $called = false;
        $this->dispatcher->listen('Tests\Events\Order*', function (EventInterface $e) use (&$called): void {
            $called = true;
        });

        $this->dispatcher->dispatch(new UserCreated());

        $this->assertFalse($called);
    }

    /**
     * '*' pattern matches any event class.
     *
     * @return void
     */
    public function test_wildcard_star_matches_any_event(): void
    {
        $event = $this->makeEvent();
        $called = false;

        $this->dispatcher->listen('*', function (EventInterface $e) use (&$called): void {
            $called = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertTrue($called);
    }

    /**
     * Wildcard and exact listener for same event both fire, priority respected.
     *
     * @return void
     */
    public function test_wildcard_and_exact_listener_both_fire_with_priority(): void
    {
        $order = [];

        $this->dispatcher->listen('Tests\Events\Order*', function (EventInterface $e) use (&$order): void {
            $order[] = 'wildcard';
        }, 5);

        $this->dispatcher->listen(OrderPlaced::class, function (EventInterface $e) use (&$order): void {
            $order[] = 'exact';
        }, 10);

        $this->dispatcher->dispatch(new OrderPlaced());

        $this->assertSame(['exact', 'wildcard'], $order);
    }

    /**
     * forget() for the exact pattern key removes wildcard listeners for that pattern.
     *
     * @return void
     */
    public function test_forget_removes_wildcard_pattern_listeners(): void
    {
        $event = $this->makeEvent();
        $called = false;

        $this->dispatcher->listen('*', function (EventInterface $e) use (&$called): void {
            $called = true;
        });
        $this->dispatcher->forget('*');

        $this->dispatcher->dispatch($event);

        $this->assertFalse($called);
    }

    // ─── Async dispatch ───────────────────────────────────────────────────────

    /**
     * With queue set, async dispatch pushes AsyncEventJob onto queue.
     *
     * @return void
     */
    public function test_async_dispatch_pushes_job_to_queue(): void
    {
        $event = $this->makeEvent();

        $queue = new class () implements QueueInterface {
            /** @var list<JobInterface> */
            public array $pushed = [];

            /**
             * @param JobInterface $job
             *
             * @return void
             */
            public function push(JobInterface $job): void
            {
                $this->pushed[] = $job;
            }

            /**
             * @param string $queue
             *
             * @return JobInterface|null
             */
            public function pop(string $queue = 'default'): ?JobInterface
            {
                return null;
            }

            /**
             * @param string $queue
             *
             * @return int
             */
            public function size(string $queue = 'default'): int
            {
                return 0;
            }

            /**
             * @param JobInterface $job
             * @param \Throwable   $exception
             *
             * @return void
             */
            public function failed(JobInterface $job, \Throwable $exception): void
            {
            }
        };

        $this->dispatcher->setQueue($queue);
        $this->dispatcher->listen($event::class, function (EventInterface $e): void {
            // Should not be called.
        });

        $this->dispatcher->dispatch($event, async: true);

        $this->assertCount(1, $queue->pushed);
        $this->assertInstanceOf(AsyncEventJob::class, $queue->pushed[0]);
    }

    /**
     * Sync dispatch (async: false) runs listeners even when queue is set.
     *
     * @return void
     */
    public function test_sync_dispatch_runs_listeners_when_queue_is_set(): void
    {
        $event = $this->makeEvent();
        $called = false;

        $queue = new class () implements QueueInterface {
            /**
             * @param JobInterface $job
             *
             * @return void
             */
            public function push(JobInterface $job): void
            {
            }

            /**
             * @param string $queue
             *
             * @return JobInterface|null
             */
            public function pop(string $queue = 'default'): ?JobInterface
            {
                return null;
            }

            /**
             * @param string $queue
             *
             * @return int
             */
            public function size(string $queue = 'default'): int
            {
                return 0;
            }

            /**
             * @param JobInterface $job
             * @param \Throwable   $exception
             *
             * @return void
             */
            public function failed(JobInterface $job, \Throwable $exception): void
            {
            }
        };

        $this->dispatcher->setQueue($queue);
        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$called): void {
            $called = true;
        });

        $this->dispatcher->dispatch($event, async: false);

        $this->assertTrue($called);
    }

    /**
     * Without a queue, async: true falls back to sync dispatch.
     *
     * @return void
     */
    public function test_async_dispatch_without_queue_falls_back_to_sync(): void
    {
        $event = $this->makeEvent();
        $called = false;

        $this->dispatcher->listen($event::class, function (EventInterface $e) use (&$called): void {
            $called = true;
        });

        $this->dispatcher->dispatch($event, async: true);

        $this->assertTrue($called);
    }
}
