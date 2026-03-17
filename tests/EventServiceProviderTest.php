<?php

declare(strict_types=1);

namespace Tests\Events;

use EzPhp\Application\Application;
use EzPhp\Events\Event;
use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Events\EventServiceProvider;
use EzPhp\Events\ListenerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\ApplicationTestCase;

/**
 * Class EventServiceProviderTest
 *
 * @package Tests\Events
 */
#[CoversClass(EventServiceProvider::class)]
#[UsesClass(Event::class)]
#[UsesClass(EventDispatcher::class)]
final class EventServiceProviderTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(EventServiceProvider::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Event::resetDispatcher();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_event_dispatcher_is_bound_in_container(): void
    {
        $this->assertInstanceOf(EventDispatcher::class, $this->app()->make(EventDispatcher::class));
    }

    /**
     * @return void
     */
    public function test_static_facade_is_wired_after_bootstrap(): void
    {
        $this->assertSame($this->app()->make(EventDispatcher::class), Event::getDispatcher());
    }

    /**
     * @return void
     */
    public function test_listeners_registered_after_bootstrap_are_dispatched(): void
    {
        $event = new class () implements EventInterface {
        };
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
    public function test_same_dispatcher_instance_used_by_facade_and_container(): void
    {
        $event = new class () implements EventInterface {
        };
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

        // Register via container-resolved dispatcher.
        $this->app()->make(EventDispatcher::class)->listen($event::class, $listener);

        // Dispatch via static facade — should use the same underlying instance.
        Event::dispatch($event);

        $this->assertTrue($listener->called);
    }
}
