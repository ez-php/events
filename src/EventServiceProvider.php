<?php

declare(strict_types=1);

namespace EzPhp\Events;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;

/**
 * Class EventServiceProvider
 *
 * Binds the EventDispatcher singleton and wires it to the Event static facade.
 *
 * Application event listeners should be registered in the boot() method of a
 * dedicated provider that extends this one or in any other service provider:
 *
 *   class AppEventServiceProvider extends ServiceProvider
 *   {
 *       public function boot(): void
 *       {
 *           Event::listen(UserCreated::class, new SendWelcomeEmail());
 *       }
 *   }
 *
 * @package EzPhp\Events
 */
final class EventServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(EventDispatcher::class, function (ContainerInterface $app): EventDispatcher {
            $dispatcher = new EventDispatcher();
            Event::setDispatcher($dispatcher);

            return $dispatcher;
        });
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function boot(): void
    {
        // Eagerly resolve so that the static facade is wired before
        // any other provider's boot() method calls Event::listen().
        $this->app->make(EventDispatcher::class);
    }
}
