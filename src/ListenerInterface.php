<?php

declare(strict_types=1);

namespace EzPhp\Events;

/**
 * Interface ListenerInterface
 *
 * Contract for event listeners.
 *
 * Example:
 *   class SendWelcomeEmail implements ListenerInterface
 *   {
 *       public function handle(EventInterface $event): void
 *       {
 *           // send email...
 *       }
 *   }
 *
 * @package EzPhp\Events
 */
interface ListenerInterface
{
    /**
     * @param EventInterface $event
     *
     * @return void
     */
    public function handle(EventInterface $event): void;
}
