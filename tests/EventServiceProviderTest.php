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

// ─── Config-test fixtures ─────────────────────────────────────────────────────
// Named (non-anonymous) classes are required so their class-string can be
// written into a PHP config file and resolved by the container.

/**
 * Minimal event used to verify config-based listener registration.
 */
final class ConfigTestEvent implements EventInterface
{
}

/**
 * Spy listener. Uses a static flag so the test can inspect whether handle()
 * was called after bootstrapping a fresh Application.
 */
final class ConfigListenerSpy implements ListenerInterface
{
    public static bool $called = false;

    /**
     * @param EventInterface $event
     *
     * @return void
     */
    public function handle(EventInterface $event): void
    {
        self::$called = true;
    }
}

/**
 * Second spy for multi-listener tests.
 */
final class ConfigListenerSpyB implements ListenerInterface
{
    public static bool $called = false;

    /**
     * @param EventInterface $event
     *
     * @return void
     */
    public function handle(EventInterface $event): void
    {
        self::$called = true;
    }
}

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
     * @throws \ReflectionException
     */
    public function test_event_dispatcher_is_bound_in_container(): void
    {
        $this->assertInstanceOf(EventDispatcher::class, $this->app()->make(EventDispatcher::class));
    }

    /**
     * @return void
     * @throws \ReflectionException
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
     * @throws \ReflectionException
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

    // ─── Config-based listener registration ──────────────────────────────────

    /**
     * Helper: bootstrap a fresh Application whose config/events.php contains
     * the given listeners map.
     *
     * @param array<string, list<string>> $listenersMap
     *
     * @return Application
     */
    private function makeAppWithEventsConfig(array $listenersMap): Application
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-php-events-cfg-' . uniqid('', true);
        mkdir($basePath . DIRECTORY_SEPARATOR . 'config', 0o777, true);

        $entries = [];

        foreach ($listenersMap as $event => $listeners) {
            $listenerList = implode(', ', array_map(static fn (string $l): string => "'{$l}'", $listeners));
            $entries[] = "'{$event}' => [{$listenerList}]";
        }

        $body = implode(",\n        ", $entries);
        file_put_contents(
            $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'events.php',
            "<?php\nreturn ['listeners' => [{$body}]];\n",
        );

        $app = new Application($basePath);
        $app->register(EventServiceProvider::class);
        $app->bootstrap();

        return $app;
    }

    /**
     * @return void
     */
    public function test_config_listener_is_called_on_dispatch(): void
    {
        ConfigListenerSpy::$called = false;

        $this->makeAppWithEventsConfig([
            ConfigTestEvent::class => [ConfigListenerSpy::class],
        ]);

        Event::dispatch(new ConfigTestEvent());

        $this->assertTrue(ConfigListenerSpy::$called);

        Event::resetDispatcher();
    }

    /**
     * @return void
     */
    public function test_multiple_config_listeners_for_one_event_are_all_called(): void
    {
        ConfigListenerSpy::$called = false;
        ConfigListenerSpyB::$called = false;

        $this->makeAppWithEventsConfig([
            ConfigTestEvent::class => [ConfigListenerSpy::class, ConfigListenerSpyB::class],
        ]);

        Event::dispatch(new ConfigTestEvent());

        $this->assertTrue(ConfigListenerSpy::$called);
        $this->assertTrue(ConfigListenerSpyB::$called);

        Event::resetDispatcher();
    }

    /**
     * @return void
     */
    public function test_missing_events_config_does_not_throw(): void
    {
        // ApplicationTestCase already uses an empty config dir — simply verify
        // that the provider boots without error when no events.php exists.
        $this->assertInstanceOf(EventDispatcher::class, $this->app()->make(EventDispatcher::class));
    }

    /**
     * @return void
     */
    public function test_empty_listeners_map_in_config_does_not_throw(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-php-events-empty-' . uniqid('', true);
        mkdir($basePath . DIRECTORY_SEPARATOR . 'config', 0o777, true);

        file_put_contents(
            $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'events.php',
            "<?php\nreturn ['listeners' => []];\n",
        );

        $app = new Application($basePath);
        $app->register(EventServiceProvider::class);
        $app->bootstrap();

        $this->assertInstanceOf(EventDispatcher::class, $app->make(EventDispatcher::class));

        Event::resetDispatcher();
    }
}
