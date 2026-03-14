<?php

declare(strict_types=1);

namespace Tests\Events;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Console\Console;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Console\Input;
use EzPhp\Console\Output;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Events\Event;
use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Events\EventServiceProvider;
use EzPhp\Events\ListenerInterface;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Migration\Migrator;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use EzPhp\Routing\RouterServiceProvider;
use EzPhp\ServiceProvider\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionException;
use Tests\DatabaseTestCase;

/**
 * Class EventServiceProviderTest
 *
 * @package Tests\Events
 */
#[CoversClass(EventServiceProvider::class)]
#[UsesClass(Event::class)]
#[UsesClass(EventDispatcher::class)]
#[UsesClass(Application::class)]
#[UsesClass(Container::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]

#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(Console::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]

#[UsesClass(MakeControllerCommand::class)]
#[UsesClass(MakeMiddlewareCommand::class)]
#[UsesClass(MakeProviderCommand::class)]
#[UsesClass(Input::class)]
#[UsesClass(Output::class)]
#[UsesClass(ServiceProvider::class)]
final class EventServiceProviderTest extends DatabaseTestCase
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
