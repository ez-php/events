<?php

declare(strict_types=1);

namespace Tests\Events;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\Events\Event;
use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Events\EventServiceProvider;
use EzPhp\Events\ListenerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
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
     * Build a minimal container stub and boot the provider against it.
     */
    private function makeBootedContainer(): ContainerInterface
    {
        $container = new class () implements ContainerInterface {
            /** @var array<string, callable> */
            private array $bindings = [];

            /** @var array<string, object> */
            private array $instances = [];

            public function bind(string $abstract, string|callable|null $factory = null): void
            {
                if (is_callable($factory)) {
                    $this->bindings[$abstract] = $factory;
                }
            }

            public function instance(string $abstract, object $instance): void
            {
                $this->instances[$abstract] = $instance;
            }

            /**
             * @template T of object
             * @param class-string<T> $abstract
             * @return T
             */
            public function make(string $abstract): mixed
            {
                if (isset($this->instances[$abstract])) {
                    /** @var T */
                    return $this->instances[$abstract];
                }

                if (isset($this->bindings[$abstract])) {
                    /** @var T */
                    return $this->instances[$abstract] = ($this->bindings[$abstract])($this);
                }

                throw new \RuntimeException("No binding registered for {$abstract}.");
            }
        };

        $provider = new EventServiceProvider($container);
        $provider->register();
        $provider->boot();

        return $container;
    }

    /**
     * @return void
     */
    public function test_event_dispatcher_is_bound_in_container(): void
    {
        $container = $this->makeBootedContainer();

        $this->assertInstanceOf(EventDispatcher::class, $container->make(EventDispatcher::class));
    }

    /**
     * @return void
     */
    public function test_static_facade_is_wired_after_bootstrap(): void
    {
        $container = $this->makeBootedContainer();

        $this->assertSame($container->make(EventDispatcher::class), Event::getDispatcher());
    }

    /**
     * @return void
     */
    public function test_listeners_registered_after_bootstrap_are_dispatched(): void
    {
        $this->makeBootedContainer();

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
        $container = $this->makeBootedContainer();

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
        $container->make(EventDispatcher::class)->listen($event::class, $listener);

        // Dispatch via static facade — should use the same underlying instance.
        Event::dispatch($event);

        $this->assertTrue($listener->called);
    }
}
