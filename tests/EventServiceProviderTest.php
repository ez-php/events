<?php

declare(strict_types=1);

namespace Tests\Events;

use EzPhp\Application\Application;
use EzPhp\Events\Event;
use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Events\EventServiceProvider;
use EzPhp\Events\ListenerInterface;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionException;
use Tests\TestCase;

/**
 * Class EventServiceProviderTest
 *
 * @package Tests\Events
 */
#[CoversClass(EventServiceProvider::class)]
#[UsesClass(Event::class)]
#[UsesClass(EventDispatcher::class)]
final class EventServiceProviderTest extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Event::resetDispatcher();
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_event_dispatcher_is_bound_in_container(): void
    {
        $app = new Application();
        $app->register(EventServiceProvider::class);
        $app->bootstrap();

        $dispatcher = $app->make(EventDispatcher::class);

        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    /**
     * @throws ReflectionException
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_static_facade_is_wired_after_bootstrap(): void
    {
        $app = new Application();
        $app->register(EventServiceProvider::class);
        $app->bootstrap();

        $containerDispatcher = $app->make(EventDispatcher::class);

        $this->assertSame($containerDispatcher, Event::getDispatcher());
    }

    /**
     */
    public function test_listeners_registered_after_bootstrap_are_dispatched(): void
    {
        $app = new Application();
        $app->register(EventServiceProvider::class);
        $app->bootstrap();

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
     * @throws ReflectionException
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_same_dispatcher_instance_used_by_facade_and_container(): void
    {
        $app = new Application();
        $app->register(EventServiceProvider::class);
        $app->bootstrap();

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
        $app->make(EventDispatcher::class)->listen($event::class, $listener);

        // Dispatch via static facade — should use the same underlying instance.
        Event::dispatch($event);

        $this->assertTrue($listener->called);
    }
}
