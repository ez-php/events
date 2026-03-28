<?php

declare(strict_types=1);

namespace EzPhp\Events;

/**
 * Interface StoppableEventInterface
 *
 * An event that can signal that no further listeners should be invoked.
 *
 * When a listener calls stopPropagation(), the EventDispatcher will stop
 * calling subsequent listeners for that dispatch cycle. Listeners may also
 * return false from a Closure to achieve the same effect on any EventInterface.
 *
 * @package EzPhp\Events
 */
interface StoppableEventInterface extends EventInterface
{
    /**
     * Return true if propagation has been stopped.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool;

    /**
     * Stop the propagation of this event to further listeners.
     *
     * @return void
     */
    public function stopPropagation(): void;
}
