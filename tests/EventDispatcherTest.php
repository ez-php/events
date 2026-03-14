<?php

declare(strict_types=1);

namespace Tests\Events;

use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Events\ListenerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class EventDispatcherTest
 *
 * @package Tests\Events
 */
#[CoversClass(EventDispatcher::class)]
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
}
