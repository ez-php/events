<?php

declare(strict_types=1);

namespace EzPhp\Events;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;

/**
 * Class EventServiceProvider
 *
 * Binds the EventDispatcher singleton, wires it to the Event static facade,
 * and auto-registers listeners declared in config/events.php.
 *
 * Config-based registration (config/events.php):
 *
 *   return [
 *       'listeners' => [
 *           UserCreated::class => [
 *               SendWelcomeEmail::class,
 *               LogUserCreation::class,
 *           ],
 *       ],
 *   ];
 *
 * Each listener class is resolved via the container (supports autowiring).
 * Additional listeners can still be registered imperatively in the boot()
 * method of any service provider that runs after EventServiceProvider:
 *
 *   Event::listen(OrderPlaced::class, new SendOrderConfirmation());
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
     * Eagerly resolve the EventDispatcher (wires the static facade) and
     * register all listeners declared in config/events.php.
     *
     * @return void
     */
    public function boot(): void
    {
        // Eagerly resolve so that the static facade is wired before
        // any other provider's boot() method calls Event::listen().
        $dispatcher = $this->app->make(EventDispatcher::class);

        $config = $this->app->make(ConfigInterface::class);
        $raw = $config->get('events.listeners', []);

        if (!is_array($raw)) {
            return;
        }

        foreach ($raw as $eventClass => $listenerClasses) {
            if (!is_string($eventClass) || !is_array($listenerClasses)) {
                continue;
            }

            foreach ($listenerClasses as $listenerClass) {
                if (!is_string($listenerClass) || !class_exists($listenerClass)) {
                    continue;
                }

                /** @var class-string<object> $listenerClass */
                $listener = $this->app->make($listenerClass);

                if ($listener instanceof ListenerInterface) {
                    $dispatcher->listen($eventClass, $listener);
                }
            }
        }
    }
}
