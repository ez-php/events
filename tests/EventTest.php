<?php

declare(strict_types=1);

namespace Tests\Events;

use EzPhp\Events\Event;
use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Events\ListenerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class EventTest
 *
 * @package Tests\Events
 */
#[CoversClass(Event::class)]
#[UsesClass(EventDispatcher::class)]
final class EventTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Event::resetDispatcher();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Event::resetDispatcher();
        parent::tearDown();
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

    // ─── getDispatcher / setDispatcher / resetDispatcher ─────────────────────

    /**
     * @return void
     */
    public function test_get_dispatcher_creates_instance_lazily(): void
    {
        $dispatcher = Event::getDispatcher();

        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    /**
     * @return void
     */
    public function test_set_dispatcher_replaces_instance(): void
    {
        $custom = new EventDispatcher();
        Event::setDispatcher($custom);

        $this->assertSame($custom, Event::getDispatcher());
    }

    /**
     * @return void
     */
    public function test_reset_dispatcher_clears_instance(): void
    {
        $first = Event::getDispatcher();
        Event::resetDispatcher();
        $second = Event::getDispatcher();

        $this->assertNotSame($first, $second);
    }

    // ─── listen / dispatch ────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_listen_and_dispatch_with_closure(): void
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

        Event::listen($event::class, function (EventInterface $e) use ($listener): void {
            $listener->handle($e);
        });

        Event::dispatch($event);

        $this->assertTrue($listener->called);
    }

    /**
     * @return void
     */
    public function test_listen_and_dispatch_with_listener_interface(): void
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

        Event::listen($event::class, $listener);
        Event::dispatch($event);

        $this->assertTrue($listener->called);
    }

    /**
     * @return void
     */
    public function test_dispatch_without_listeners_does_not_throw(): void
    {
        $this->expectNotToPerformAssertions();

        Event::dispatch($this->makeEvent());
    }

    // ─── forget ───────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_forget_removes_listeners(): void
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

        Event::listen($event::class, $listener);
        Event::forget($event::class);
        Event::dispatch($event);

        $this->assertFalse($listener->called);
    }

    // ─── multiple dispatches ──────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_listener_is_called_on_every_dispatch(): void
    {
        $event = $this->makeEvent();
        $counter = new class () implements ListenerInterface {
            public int $count = 0;

            /**
             * @param EventInterface $event
             *
             * @return void
             */
            public function handle(EventInterface $event): void
            {
                $this->count++;
            }
        };

        Event::listen($event::class, $counter);

        Event::dispatch($event);
        Event::dispatch($event);
        Event::dispatch($event);

        $this->assertSame(3, $counter->count);
    }

    // ─── Wildcard via facade ──────────────────────────────────────────────────

    /**
     * Event::listen() with wildcard pattern delegates to dispatcher.
     *
     * @return void
     */
    public function test_listen_with_wildcard_pattern_delegates_to_dispatcher(): void
    {
        $event = $this->makeEvent();
        $called = false;

        Event::listen('*', function (EventInterface $e) use (&$called): void {
            $called = true;
        });

        Event::dispatch($event);

        $this->assertTrue($called);
    }

    // ─── Async via facade ─────────────────────────────────────────────────────

    /**
     * Event::dispatch() with async: true delegates to dispatcher.
     * Without a queue set, it falls back to sync.
     *
     * @return void
     */
    public function test_dispatch_async_delegates_to_dispatcher(): void
    {
        $event = $this->makeEvent();
        $called = false;

        Event::listen($event::class, function (EventInterface $e) use (&$called): void {
            $called = true;
        });

        // No queue set — falls back to sync.
        Event::dispatch($event, async: true);

        $this->assertTrue($called);
    }
}
